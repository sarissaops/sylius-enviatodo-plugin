<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Twig\EnviatodoAdminExtension;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class EnviatodoAdminExtensionTest extends TestCase
{
    public function testExposesTheShipmentRecordFunction(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $record = new EnviatodoShipment($shipment, 'uuid-1', '9', '11');
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $repository->expects($this->once())->method('findOneByShipment')->with($shipment)->willReturn($record);

        $extension = new EnviatodoAdminExtension($repository);

        $this->assertSame($record, $extension->getShipmentRecord($shipment));

        $names = [];
        foreach ($extension->getFunctions() as $function) {
            $names[] = $function->getName();
        }
        $this->assertContains('enviatodo_shipment_record', $names);
    }
}
