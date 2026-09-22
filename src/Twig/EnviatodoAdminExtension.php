<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Twig;

use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EnviatodoAdminExtension extends AbstractExtension
{
    public function __construct(private readonly EnviatodoShipmentRepositoryInterface $repository)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [new TwigFunction('enviatodo_shipment_record', [$this, 'getShipmentRecord'])];
    }

    public function getShipmentRecord(ShipmentInterface $shipment): ?EnviatodoShipment
    {
        return $this->repository->findOneByShipment($shipment);
    }
}
