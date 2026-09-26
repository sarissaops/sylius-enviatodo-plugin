<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * @extends ServiceEntityRepository<EnviatodoShipment>
 */
final class EnviatodoShipmentRepository extends ServiceEntityRepository implements EnviatodoShipmentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnviatodoShipment::class);
    }

    public function findOneByShipment(ShipmentInterface $shipment): ?EnviatodoShipment
    {
        $found = $this->findOneBy(['shipment' => $shipment]);

        return $found instanceof EnviatodoShipment ? $found : null;
    }

    public function findOneByShipmentId(int $syliusShipmentId): ?EnviatodoShipment
    {
        $found = $this->findOneBy(['shipment' => $syliusShipmentId]);

        return $found instanceof EnviatodoShipment ? $found : null;
    }
}
