<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotedRates;
use Symfony\Component\Serializer\Attribute\Groups;

final class LiveRatesResult
{
    /** @param list<LiveRate> $rates */
    public function __construct(
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public string $transactionUuid,
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public array $rates = [],
    ) {
    }

    public static function fromQuotedRates(QuotedRates $quoted): self
    {
        $rates = [];
        foreach ($quoted->getRates() as $rate) {
            $rates[] = LiveRate::fromRateResult($rate);
        }

        return new self($quoted->getTransactionUuid(), $rates);
    }
}
