<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\PickupApi;
use SarissaOps\Enviatodo\Model\Pickup\PickupOrder;
use SarissaOps\Enviatodo\Model\Pickup\PickupResponse;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPickupScheduler;

final class EnviatodoPickupSchedulerTest extends TestCase
{
    private function scheduler(PickupApi $pickupApi, bool $autoSchedule = false): EnviatodoPickupScheduler
    {
        return new EnviatodoPickupScheduler($pickupApi, new NullLogger(), $autoSchedule);
    }

    /** @return array<string, mixed> */
    private function pickupData(): array
    {
        return [
            'who_delivers' => 'Juan Perez',
            'contact_phone' => '8180000000',
            'contact_email' => 'dock@example.com',
            'data' => [
                ['trx_id' => '426855', 'origin_address' => 'Zarco 940, Monterrey Centro, Monterrey, NL, 64000, MX'],
            ],
            'pickup_date' => '2026-09-23 06:13 pm',
            'pickup_place' => 2,
            'pickup_place_data' => 'OFICINA',
            'origin' => 'Zarco 940, Monterrey Centro, Monterrey, NL, 64000, MX',
            'provider_id' => '1',
            'description_reference' => 'daily run',
        ];
    }

    public function testEligibleOrdersListsPickupQueue(): void
    {
        $pickupApi = $this->createMock(PickupApi::class);
        $pickupApi->expects($this->once())->method('ordersForPickup')->with(['1'])->willReturn([
            PickupOrder::create(['id' => '1', 'tracking_id' => 'TRK1', 'provider_name' => 'FEDEX', 'provider_id' => '1', 'service_name' => 'Terrestre', 'origin' => 'Monterrey', 'created_at' => '2026-09-22']),
        ]);

        $orders = $this->scheduler($pickupApi)->eligibleOrders(['1']);

        $this->assertCount(1, $orders);
        $this->assertSame('TRK1', $orders[0]->getTrackingId());
    }

    public function testEligibleOrdersRejectsEmptyProviderLists(): void
    {
        $pickupApi = $this->createMock(PickupApi::class);
        $pickupApi->expects($this->never())->method('ordersForPickup');

        $this->expectException(\InvalidArgumentException::class);

        $this->scheduler($pickupApi)->eligibleOrders([]);
    }

    public function testScheduleBooksTheDockRun(): void
    {
        $data = $this->pickupData();
        $pickupApi = $this->createMock(PickupApi::class);
        $pickupApi->expects($this->once())->method('create')->with($data)->willReturn(PickupResponse::create([]));

        $this->assertTrue($this->scheduler($pickupApi)->schedule($data));
    }

    public function testScheduleRejectsMissingHandoverContacts(): void
    {
        $data = $this->pickupData();
        unset($data['contact_email']);
        $pickupApi = $this->createMock(PickupApi::class);
        $pickupApi->expects($this->never())->method('create');

        $this->expectException(\InvalidArgumentException::class);

        $this->scheduler($pickupApi)->schedule($data);
    }

    public function testScheduleRejectsMixedOriginsBeforeAnyTraffic(): void
    {
        $data = $this->pickupData();
        $data['data'][] = ['trx_id' => '426856', 'origin_address' => 'Other street 1, CDMX'];
        $pickupApi = $this->createMock(PickupApi::class);
        $pickupApi->expects($this->never())->method('create');

        $this->expectException(\InvalidArgumentException::class);

        $this->scheduler($pickupApi)->schedule($data);
    }

    public function testCancelCarriesTheAuditUserId(): void
    {
        $pickupApi = $this->createMock(PickupApi::class);
        $pickupApi->expects($this->once())->method('cancel')->with(29114, 'no longer needed', '7')->willReturn(PickupResponse::create([]));

        $this->assertTrue($this->scheduler($pickupApi)->cancelPickup(29114, 'no longer needed', '7'));
    }

    public function testAutoScheduleToggleIsExposed(): void
    {
        $pickupApi = $this->createMock(PickupApi::class);

        $this->assertFalse($this->scheduler($pickupApi)->isAutoScheduleEnabled());
        $this->assertTrue($this->scheduler($pickupApi, true)->isAutoScheduleEnabled());
    }
}
