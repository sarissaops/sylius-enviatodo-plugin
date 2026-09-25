<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator;

use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\DestinationAddress;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotedRates;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\RateResult;

/**
 * Framework-free rate calculator over QuoteApi. Keeps the quote uuid next to
 * the rows so callers can hold it for create_order.
 */
final class EnviatodoCalculator
{
    public function __construct(
        private readonly QuoteApi $quoteApi,
        private readonly ZipCodeApi $zipCodeApi,
    ) {
    }

    /**
     * @param array<string, mixed> $quotePayload
     */
    public function rates(array $quotePayload, ?string $providerId = null, ?string $serviceId = null): QuotedRates
    {
        if (null !== $serviceId) {
            $result = $this->quoteApi->byService($quotePayload, (int) $serviceId);
        } elseif (null !== $providerId) {
            $result = $this->quoteApi->byProvider($quotePayload, (int) $providerId);
        } else {
            $result = $this->quoteApi->all($quotePayload);
        }

        if (!$result instanceof Quote) {
            throw new \RuntimeException('QuoteApi must hydrate Quote models for rate calculation.');
        }

        $rates = [];
        foreach ($result->getRates() as $row) {
            if (!$row->isAvailable()) {
                continue;
            }
            $rates[] = RateResult::fromRateRow($row);
        }

        return new QuotedRates($result->getTransactionUuid(), $rates);
    }

    /**
     * Quotes a checkout destination: resolves the settlement from the zip-code
     * catalog, then matches one provider pair. Returns null when the catalog
     * has no settlement or the quote carries no matching available row —
     * callers turn that into a hidden method, never a zero price.
     *
     * @param array<string, mixed> $quoteConfig method configuration: full `origin` address + `package`
     * @param array{fullName: string, email: string, telephone: string, street: string, postcode: string, countryCode: string} $contact
     */
    public function rateForAddress(array $quoteConfig, array $contact, string $providerId, string $serviceId): ?RateResult
    {
        $origin = $quoteConfig['origin'] ?? null;
        $package = $quoteConfig['package'] ?? null;
        if (!\is_array($origin) || !\is_array($package)) {
            throw new \InvalidArgumentException('Enviatodo method configuration requires an "origin" address and a "package" definition.');
        }
        $missingOrigin = QuotePayload::missingKeys($origin, QuotePayload::REQUIRED_ORIGIN_KEYS);
        $missingPackage = QuotePayload::missingKeys($package, QuotePayload::REQUIRED_PACKAGE_KEYS);
        if ([] !== $missingOrigin || [] !== $missingPackage) {
            throw new \InvalidArgumentException(sprintf(
                'Enviatodo method configuration is missing keys: origin[%s] package[%s].',
                implode(',', $missingOrigin),
                implode(',', $missingPackage),
            ));
        }

        $postcode = trim($contact['postcode']);
        if ('' === $postcode) {
            return null;
        }
        $settlement = $this->zipCodeApi->show($postcode);
        if (!$settlement instanceof ZipCode || [] === $settlement->getItems()) {
            return null;
        }

        $payload = [
            'shipping_type' => $quoteConfig['shipping_type'] ?? '1',
            'quantity' => $quoteConfig['quantity'] ?? 1,
            'origin' => $origin,
            'destination' => DestinationAddress::fromParts($contact, $settlement->getItems()[0]),
            'package' => $package,
        ];

        foreach ($this->rates($payload, $providerId, $serviceId)->getRates() as $rate) {
            if ($rate->getProviderId() === $providerId && $rate->getProviderServiceId() === $serviceId) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $quotePayload
     */
    public function rateFor(array $quotePayload, string $providerId, string $serviceId): ?RateResult
    {
        foreach ($this->rates($quotePayload, $providerId, $serviceId)->getRates() as $rate) {
            if ($rate->getProviderId() === $providerId && $rate->getProviderServiceId() === $serviceId) {
                return $rate;
            }
        }

        return null;
    }
}
