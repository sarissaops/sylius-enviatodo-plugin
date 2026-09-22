<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * Sylius calculator adapter. The per-method $configuration carries the quote
 * payload under the `quote` key; the method code resolves to a provider pair
 * through the ProviderMap. Returns 0 when no live rate matches — a later rule
 * checker hides such methods instead of showing a misleading free rate.
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
            throw new \LogicException('Enviatodo calculator cannot price shipping methods outside the provider map.');
        }

        $quote = $configuration['quote'] ?? null;
        if (!\is_array($quote)) {
            throw new \InvalidArgumentException('Enviatodo calculator configuration requires a "quote" payload array.');
        }
        $payload = [];
        foreach ($quote as $key => $value) {
            if (\is_string($key)) {
                $payload[$key] = $value;
            }
        }

        $rate = $this->calculator->rateFor($payload, $mapping['provider_id'], $mapping['provider_service_id']);

        return $rate?->getAmountMinor() ?? 0;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
