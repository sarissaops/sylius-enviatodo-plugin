<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Repository;

use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Component\Shipping\Model\ShipmentInterface;

interface EnviatodoShipmentRepositoryInterface
{
    public function findOneByShipment(ShipmentInterface $shipment): ?EnviatodoShipment;

    /**
     * Finds the record for a Sylius shipment id. Never confuse with a primary
     * key lookup: this table's own id is unrelated to shipment ids.
     */
    public function findOneByShipmentId(int $syliusShipmentId): ?EnviatodoShipment;

    public function findOneByTrackingId(string $trackingId): ?EnviatodoShipment;
}
