<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Repository;

use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Component\Shipping\Model\ShipmentInterface;

interface EnviatodoShipmentRepositoryInterface
{
    public function findById(int $id): ?EnviatodoShipment;

    public function findOneByShipment(ShipmentInterface $shipment): ?EnviatodoShipment;

    /**
     * Finds the record for a Sylius shipment id. Never confuse with findById:
     * that reads this table's own primary key and returns the wrong label.
     */
    public function findOneByShipmentId(int $syliusShipmentId): ?EnviatodoShipment;
}
