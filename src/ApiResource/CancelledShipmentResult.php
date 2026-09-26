<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final class CancelledShipmentResult
{
    public function __construct(
        #[Groups(['enviatodo:admin:shipment_cancel:show'])]
        public int $shipmentId = 0,
        #[Groups(['enviatodo:admin:shipment_cancel:show'])]
        public string $trackingId = '',
        #[Groups(['enviatodo:admin:shipment_cancel:show'])]
        public string $cancelledAt = '',
        #[Groups(['enviatodo:admin:shipment_cancel:show'])]
        public string $refundedAt = '',
    ) {
    }
}
