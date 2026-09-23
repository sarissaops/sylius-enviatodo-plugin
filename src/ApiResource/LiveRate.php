<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\RateResult;
use Symfony\Component\Serializer\Attribute\Groups;

final class LiveRate
{
    public function __construct(
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public string $providerId,
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public string $providerServiceId,
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public string $providerName,
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public string $serviceName,
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public int $amountMinor,
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public string $currency,
        #[Groups(['enviatodo:shop:live_rates:show'])]
        public string $estimatedDate,
    ) {
    }

    public static function fromRateResult(RateResult $rate): self
    {
        return new self(
            $rate->getProviderId(),
            $rate->getProviderServiceId(),
            $rate->getProviderName(),
            $rate->getServiceName(),
            $rate->getAmountMinor(),
            $rate->getCurrency(),
            $rate->getEstimatedDate(),
        );
    }
}
