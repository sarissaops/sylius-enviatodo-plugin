<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class LiveRatesQuoteRequest
{
    /** @param array<string, mixed> $origin @param array<string, mixed> $destination @param array<string, mixed> $package */
    public function __construct(
        #[Groups(['enviatodo:shop:live_rates:write'])]
        #[Assert\NotBlank]
        public array $origin = [],
        #[Groups(['enviatodo:shop:live_rates:write'])]
        #[Assert\NotBlank]
        public array $destination = [],
        #[Groups(['enviatodo:shop:live_rates:write'])]
        #[Assert\NotBlank]
        public array $package = [],
        #[Groups(['enviatodo:shop:live_rates:write'])]
        #[Assert\Positive]
        public int $quantity = 1,
    ) {
    }
}
