<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

use SarissaOps\Enviatodo\Model\Quote\RateRow;

/**
 * One purchasable rate. Amounts are integer minor units (MXN centavos) because
 * that is what Sylius calculators return.
 */
final class RateResult
{
    public function __construct(
        private readonly string $providerId,
        private readonly string $providerServiceId,
        private readonly string $providerName,
        private readonly string $serviceName,
        private readonly int $amountMinor,
        private readonly string $currency,
        private readonly string $estimatedDate,
    ) {
    }

    public static function fromRateRow(RateRow $row): self
    {
        $total = 0.0;
        foreach ($row->getCharges() as $charge) {
            $total += $charge->getTotal();
        }

        return new self(
            $row->getProviderId(),
            $row->getProviderServiceId(),
            $row->getProviderName(),
            $row->getServiceName(),
            (int) round($total * 100),
            $row->getCurrency() ?? 'MXN',
            $row->getEstimatedDate(),
        );
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function getProviderServiceId(): string
    {
        return $this->providerServiceId;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getEstimatedDate(): string
    {
        return $this->estimatedDate;
    }
}
