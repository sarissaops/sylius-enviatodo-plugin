<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\BalanceApi;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Balance\Balance;
use SarissaOps\Enviatodo\Model\Order\OrderCreated;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Exception\EnviatodoOrderException;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoOrderManager;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class EnviatodoOrderManagerTest extends TestCase
{
    /** @return array{OrderApi, BalanceApi, ProviderMap, EntityManagerInterface, EnviatodoOrderManager} */
    private function manager(float $balance = 1000.0): array
    {
        $orderApi = $this->createMock(OrderApi::class);
        $balanceApi = $this->createMock(BalanceApi::class);
        $balanceApi->method('show')->willReturn(Balance::create(['balance' => $balance]));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        $manager = new EnviatodoOrderManager(
            $orderApi,
            $balanceApi,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
            $registry,
            new NullLogger(),
            false,
        );

        return [$orderApi, $balanceApi, new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]), $entityManager, $manager];
    }

    private function shipment(): ShipmentInterface
    {
        return $this->createMock(ShipmentInterface::class);
    }

    /** @return array<string, mixed> */
    private function createdData(): array
    {
        return [
            'summary' => ['sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00'],
            'guides' => [['id' => 'g1', 'insured' => false, 'tracking_id' => 'TRK1', 'provider_id' => '9']],
        ];
    }

    public function testCreateDraftPersistsQuoteUuidAndProviderPair(): void
    {
        [, , , $entityManager, $manager] = $this->manager();
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(EnviatodoShipment::class));
        $entityManager->expects($this->once())->method('flush');

        $draft = $manager->createDraft($this->shipment(), 'uuid-1', 'enviatodo_estafeta');

        $this->assertSame('uuid-1', $draft->getQuoteUuid());
        $this->assertSame('9', $draft->getProviderId());
        $this->assertSame('11', $draft->getProviderServiceId());
        $this->assertFalse($draft->isCompleted());
    }

    public function testCreateDraftRejectsUnmappedMethods(): void
    {
        [, , , , $manager] = $this->manager();

        $this->expectException(\LogicException::class);
        $manager->createDraft($this->shipment(), 'uuid-1', 'flat_rate');
    }

    public function testCreateDraftRejectsEmptyUuids(): void
    {
        [, , , , $manager] = $this->manager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->createDraft($this->shipment(), '', 'enviatodo_estafeta');
    }

    public function testCompleteDraftBuysTheLabelAndStoresTracking(): void
    {
        [$orderApi, , , $entityManager, $manager] = $this->manager();
        $orderApi->expects($this->once())->method('create')->with('uuid-1', '9', '11', false)
            ->willReturn(OrderCreated::create($this->createdData()));
        $entityManager->expects($this->once())->method('flush');

        $draft = new EnviatodoShipment($this->shipment(), 'uuid-1', '9', '11');
        $manager->completeDraft($draft);

        $this->assertTrue($draft->isCompleted());
        $this->assertSame('TRK1', $draft->getTrackingId());
        $this->assertSame(23200, $draft->getAmountMinor());
    }

    public function testCompleteDraftRefusesEmptyBalances(): void
    {
        [$orderApi, , , , $manager] = $this->manager(0.0);
        $orderApi->expects($this->never())->method('create');

        $this->expectException(EnviatodoOrderException::class);

        $manager->completeDraft(new EnviatodoShipment($this->shipment(), 'uuid-1', '9', '11'));
    }

    public function testCompleteDraftRejectsGuidelessResponses(): void
    {
        [$orderApi, , , , $manager] = $this->manager();
        $orderApi->method('create')->willReturn(OrderCreated::create([
            'summary' => ['sub_total' => '0', 'tax' => '0', 'total' => '0'],
            'guides' => [],
        ]));

        $this->expectException(EnviatodoOrderException::class);

        $manager->completeDraft(new EnviatodoShipment($this->shipment(), 'uuid-1', '9', '11'));
    }

    public function testCompleteDraftRejectsNonModelHydration(): void
    {
        [$orderApi, , , , $manager] = $this->manager();
        $orderApi->method('create')->willReturn([]);

        $this->expectException(\RuntimeException::class);

        $manager->completeDraft(new EnviatodoShipment($this->shipment(), 'uuid-1', '9', '11'));
    }
}
