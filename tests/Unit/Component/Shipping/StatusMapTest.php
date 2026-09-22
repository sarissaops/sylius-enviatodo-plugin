<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\StatusMap;

final class StatusMapTest extends TestCase
{
    public function testMapsOrderStatusesToHandoverKeys(): void
    {
        $map = new StatusMap();

        $this->assertSame('label_created', $map->forOrder(41));
        $this->assertSame('in_transit', $map->forOrder(42));
        $this->assertSame('cancelled', $map->forOrder(43));
        $this->assertSame('delivered', $map->forOrder(44));
        $this->assertSame('in_pickup', $map->forOrder(59));
        $this->assertSame('in_return', $map->forOrder(68));
        $this->assertSame('incident', $map->forOrder(69));
    }

    public function testMapsPickupStatusesToHandoverKeys(): void
    {
        $map = new StatusMap();

        $this->assertSame('pickup_pending', $map->forPickup(60));
        $this->assertSame('pickup_created', $map->forPickup(61));
        $this->assertSame('pickup_cancelled', $map->forPickup(62));
        $this->assertSame('pickup_rescheduled', $map->forPickup(71));
    }

    public function testUnknownCodesNeverBreakSynchronization(): void
    {
        $map = new StatusMap();

        $this->assertSame('unknown', $map->forOrder(999));
        $this->assertSame('unknown', $map->forPickup(999));
    }
}
