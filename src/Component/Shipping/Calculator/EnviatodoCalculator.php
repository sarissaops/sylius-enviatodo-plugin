<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator;

use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotedRates;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\RateResult;

/**
 * Framework-free rate calculator over QuoteApi. Keeps the quote uuid next to
 * the rows so callers can hold it for create_order.
 */
final class EnviatodoCalculator
{
    public function __construct(private readonly QuoteApi $quoteApi)
    {
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
