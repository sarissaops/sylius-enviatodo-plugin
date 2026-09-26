<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CancelShipmentRequest
{
    public function __construct(
        #[Groups(['enviatodo:admin:shipment_cancel:write'])]
        #[Assert\NotBlank]
        public string $reasonValue = '',
        #[Groups(['enviatodo:admin:shipment_cancel:write'])]
        #[Assert\NotBlank]
        public string $reasonText = '',
    ) {
    }
}
