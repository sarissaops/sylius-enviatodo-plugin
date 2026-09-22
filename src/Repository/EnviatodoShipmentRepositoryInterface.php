<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Repository;

use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Component\Shipping\Model\ShipmentInterface;

interface EnviatodoShipmentRepositoryInterface
{
    public function findById(int $id): ?EnviatodoShipment;

    public function findOneByShipment(ShipmentInterface $shipment): ?EnviatodoShipment;
}
