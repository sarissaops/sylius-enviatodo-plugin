<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Order\Order;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\StatusMap;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoStatusSynchronizer;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class EnviatodoStatusSynchronizerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function orderRow(string $statusName, string $trackingId): array
    {
        return [
            'trx_id' => '162199', 'shipping_type' => 'package', 'status_name' => $statusName,
            'created_at' => '02-10-2023', 'trx_uuid' => 'u', 'trx_type' => 'rates-batch-store', 'user_id' => 1595,
            'order_detail' => ['guide' => ['tracking_id' => $trackingId]],
        ];
    }

    /** @return array{EnviatodoStatusSynchronizer, EnviatodoShipmentRepositoryInterface} */
    private function synchronizer(?Order $order = null): array
    {
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->method('filter')->willReturn(null === $order ? [] : [$order]);
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);
        $synchronizer = new EnviatodoStatusSynchronizer($orderApi, new StatusMap(), $repository, $registry, new NullLogger());

        return [$synchronizer, $repository];
    }

    private function record(string $trackingId): EnviatodoShipment
    {
        $record = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $record->complete($trackingId, 23200);

        return $record;
    }

    public function testSyncsDeliveredStatus(): void
    {
        [$synchronizer, $repository] = $this->synchronizer(Order::create($this->orderRow('Guía entregada', 'TRK1')));
        $record = $this->record('TRK1');
        $repository->method('findOneByTrackingId')->with('TRK1')->willReturn($record);

        $summary = $synchronizer->sync(['status' => 'all']);

        $this->assertSame(['updated' => 1, 'skipped' => 0, 'unknown' => 0, 'failed' => 0], $summary);
        $this->assertSame(EnviatodoShipment::HANDOVER_DELIVERED, $record->getHandover());
    }

    public function testSkipsUnknownStatusNames(): void
    {
        [$synchronizer, $repository] = $this->synchronizer(Order::create($this->orderRow('En camino', 'TRK1')));
        $repository->expects($this->never())->method('findOneByTrackingId');

        $summary = $synchronizer->sync([]);

        $this->assertSame(0, $summary['updated']);
        $this->assertSame(1, $summary['unknown']);
    }

    public function testSkipsUnmatchedTrackingIds(): void
    {
        [$synchronizer, $repository] = $this->synchronizer(Order::create($this->orderRow('Guía entregada', 'TRK9')));
        $repository->method('findOneByTrackingId')->willReturn(null);

        $summary = $synchronizer->sync([]);

        $this->assertSame(1, $summary['skipped']);
    }

    public function testNeverRegressesTerminalStates(): void
    {
        [$synchronizer, $repository] = $this->synchronizer(Order::create($this->orderRow('Guía en transito', 'TRK1')));
        $record = $this->record('TRK1');
        $record->markDelivered();
        $repository->method('findOneByTrackingId')->willReturn($record);

        $summary = $synchronizer->sync([]);

        $this->assertSame(EnviatodoShipment::HANDOVER_DELIVERED, $record->getHandover());
        $this->assertSame(1, $summary['skipped']);
    }

    public function testNeverOverwritesBranchDropOff(): void
    {
        [$synchronizer, $repository] = $this->synchronizer(Order::create($this->orderRow('Guía en transito', 'TRK1')));
        $record = $this->record('TRK1');
        $record->markDroppedAtBranch('branch-7');
        $repository->method('findOneByTrackingId')->willReturn($record);

        $summary = $synchronizer->sync([]);

        $this->assertSame(EnviatodoShipment::HANDOVER_DROPPED_AT_BRANCH, $record->getHandover());
        $this->assertSame(1, $summary['skipped']);
    }

    public function testRecordsIncidentsWithRawStatusName(): void
    {
        [$synchronizer, $repository] = $this->synchronizer(Order::create($this->orderRow('Incidencia en guía', 'TRK1')));
        $record = $this->record('TRK1');
        $repository->method('findOneByTrackingId')->willReturn($record);

        $synchronizer->sync([]);

        $this->assertSame(EnviatodoShipment::HANDOVER_INCIDENT, $record->getHandover());
        $this->assertSame('Incidencia en guía', $record->getHandoverDetail());
    }

    public function testPreviewResolvesWithoutWriting(): void
    {
        [$synchronizer, $repository] = $this->synchronizer(Order::create($this->orderRow('Guía entregada', 'TRK1')));
        $repository->expects($this->never())->method('findOneByTrackingId');

        $preview = $synchronizer->preview([]);

        $this->assertCount(1, $preview);
        $this->assertSame('TRK1', $preview[0]['tracking_id']);
        $this->assertSame(44, $preview[0]['code']);
        $this->assertSame('delivered', $preview[0]['handover']);
    }
}
