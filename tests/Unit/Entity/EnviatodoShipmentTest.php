<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class EnviatodoShipmentTest extends TestCase
{
    public function testStartsAsUncompletedDraft(): void
    {
        $draft = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');

        $this->assertFalse($draft->isCompleted());
        $this->assertSame('label_created', $draft->getHandover());
        $this->assertNull($draft->getHandoverDetail());
        $this->assertNull($draft->getTrackingId());
    }

    public function testBranchDropOffNeedsNoApiCall(): void
    {
        $draft = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $draft->complete('TRK1', 23200);

        $draft->markDroppedAtBranch('Sucursal Centro');

        $this->assertTrue($draft->isCompleted());
        $this->assertSame('dropped_at_branch', $draft->getHandover());
        $this->assertSame('Sucursal Centro', $draft->getHandoverDetail());
    }

    public function testPickupAndTransitMilestones(): void
    {
        $draft = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');

        $draft->markInPickup();
        $this->assertSame('in_pickup', $draft->getHandover());

        $draft->markInTransit();
        $this->assertSame('in_transit', $draft->getHandover());
        $this->assertNull($draft->getHandoverDetail());

        $draft->markDelivered();
        $this->assertSame('delivered', $draft->getHandover());
    }
}
