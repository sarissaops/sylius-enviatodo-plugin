<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Component\Shipping\Model\ShipmentInterface;

interface EnviatodoOrderManagerInterface
{
    public function createDraft(ShipmentInterface $shipment, string $quoteUuid, string $methodCode): EnviatodoShipment;

    public function completeDraft(EnviatodoShipment $draft): void;

    /**
     * @param array<mixed> $quoteConfig
     */
    public function purchaseForPaidShipment(ShipmentInterface $shipment, array $quoteConfig): void;
}
