<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\NoRateAvailableException;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ShipmentContact;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * Sylius calculator adapter. Quotes the order's shipping address live: the
 * per-method $configuration carries the warehouse `origin` (full address),
 * the `package`, and an optional `contact.telephone` shop fallback (checkout
 * collects no phone) under the `quote` key; the method code resolves to a
 * provider pair through the ProviderMap. Throws NoRateAvailableException when
 * no live rate matches — Sylius hides such methods instead of showing a
 * misleading free rate.
 */
final class EnviatodoShippingCalculator implements CalculatorInterface
{
    public const TYPE = 'enviatodo';

    public function __construct(
        private readonly EnviatodoCalculator $calculator,
        private readonly ProviderMap $providerMap,
    ) {
    }

    public function calculate(ShipmentInterface $subject, array $configuration): int
    {
        $code = $subject->getMethod()?->getCode();
        $mapping = null !== $code ? $this->providerMap->getProviderService($code) : null;
        if (null === $mapping) {
            throw new NoRateAvailableException('Enviatodo calculator cannot price shipping methods outside the provider map.');
        }

        $quote = $configuration['quote'] ?? null;
        if (!\is_array($quote)) {
            throw new NoRateAvailableException('Enviatodo calculator configuration requires a "quote" payload array.');
        }
        $quoteConfig = QuotePayload::normalize($quote);

        try {
            $rate = $this->calculator->rateForAddress(
                $quoteConfig,
                ShipmentContact::fromShipment($subject, ShipmentContact::fallbackTelephone($quoteConfig)),
                $mapping['provider_id'],
                $mapping['provider_service_id'],
            );
        } catch (NoRateAvailableException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new NoRateAvailableException(
                'Enviatodo cannot quote this destination: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }
        if (null === $rate) {
            throw new NoRateAvailableException(sprintf(
                'No Enviatodo rate for provider "%s" service "%s".',
                $mapping['provider_id'],
                $mapping['provider_service_id'],
            ));
        }

        return $rate->getAmountMinor();
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
