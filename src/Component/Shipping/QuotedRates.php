<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

/**
 * Available rates of one quote call plus the transaction uuid that a later
 * create_order call needs. The uuid must be held, never re-quoted blindly.
 */
final class QuotedRates
{
    /** @param list<RateResult> $rates */
    public function __construct(
        private readonly string $transactionUuid,
        private readonly array $rates,
    ) {
    }

    public function getTransactionUuid(): string
    {
        return $this->transactionUuid;
    }

    /** @return list<RateResult> */
    public function getRates(): array
    {
        return $this->rates;
    }
}
