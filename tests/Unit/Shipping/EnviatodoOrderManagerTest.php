<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\BalanceApi;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Model\Balance\Balance;
use SarissaOps\Enviatodo\Model\Order\OrderCreated;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotedRates;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\RateResult;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Exception\EnviatodoOrderException;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoOrderManager;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface as CoreShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface as CoreShippingMethodInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class EnviatodoOrderManagerTest extends TestCase
{
    /** @return array{OrderApi, BalanceApi, EnviatodoCalculator, EntityManagerInterface, EnviatodoOrderManager} */
    private function manager(float $balance = 1000.0): array
    {
        $orderApi = $this->createMock(OrderApi::class);
        $balanceApi = $this->createMock(BalanceApi::class);
        $balanceApi->method('show')->willReturn(Balance::create(['balance' => $balance]));
        $calculator = new EnviatodoCalculator($this->createMock(QuoteApi::class), $this->createMock(ZipCodeApi::class));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        $manager = new EnviatodoOrderManager(
            $orderApi,
            $balanceApi,
            $calculator,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
            $registry,
            new NullLogger(),
            false,
        );

        return [$orderApi, $balanceApi, $calculator, $entityManager, $manager];
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
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(EnviatodoShipment::class));
        $entityManager->expects($this->once())->method('flush');

        $draft = $manager->createDraft($this->shipment(), 'uuid-1', 'enviatodo_estafeta');

        $this->assertSame('uuid-1', $draft->getQuoteUuid());
        $this->assertSame('9', $draft->getProviderId());
        $this->assertSame('11', $draft->getProviderServiceId());
        $this->assertFalse($draft->isCompleted());
    }

    public function testCreateDraftRefreshesExistingRows(): void
    {
        [, , , $entityManager, $manager] = $this->manager();
        $existing = new EnviatodoShipment($this->shipment(), 'uuid-old', '9', '11');
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $draft = $manager->createDraft($this->shipment(), 'uuid-new', 'enviatodo_estafeta');

        $this->assertSame($existing, $draft);
        $this->assertSame('uuid-new', $draft->getQuoteUuid());
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
        $this->assertSame('g1', $draft->getGuideId());
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

    /** @return array<string, mixed> */
    private function quoteConfig(): array
    {
        return [
            'origin' => [
                'address_type_id' => '1', 'full_name' => 'Tienda', 'email' => 't@example.com',
                'telephone' => '8180808080', 'street' => 'Street 1', 'ext_number' => '1', 'int_number' => '',
                'zip_code' => '64000', 'suburb' => 'Centro', 'municipality' => 'Monterrey', 'town' => 'Monterrey',
                'state' => 'Nuevo Leon', 'state_code' => 'NL', 'country_code' => 'MX',
            ],
            'package' => [
                'product_type' => '01010101', 'unit_type' => 'X1A', 'package_content' => 'books',
                'amount_pkg' => '200', 'height' => 10, 'width' => 10, 'length' => 10, 'weight' => 1,
                'real_weight' => '1.00', 'volumetric_weight' => '1', 'bill_weight' => '1', 'default_pkg' => '0',
            ],
        ];
    }

    private function paidShipment(): CoreShipmentInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('John');
        $address->method('getLastName')->willReturn('Doe');
        $address->method('getPhoneNumber')->willReturn('5555555555');
        $address->method('getStreet')->willReturn('Street 10');
        $address->method('getPostcode')->willReturn('06600');
        $address->method('getCountryCode')->willReturn('MX');
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('john@example.com');
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getCustomer')->willReturn($customer);
        $method = $this->createMock(CoreShippingMethodInterface::class);
        $method->method('getCode')->willReturn('enviatodo_estafeta');
        $shipment = $this->createMock(CoreShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($method);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }

    private function quoted(string $uuid = 'uuid-live', int $amountMinor = 23200): QuotedRates
    {
        return new QuotedRates($uuid, [
            new RateResult('9', '11', 'ESTAFETA', 'Aéreo', $amountMinor, 'MXN', '2026-01-03'),
        ]);
    }

    /** @param array<string, mixed> $createdData */
    private function purchaseManager(
        EntityManagerInterface $entityManager,
        Quote $quote,
        float $balance,
        array $createdData,
    ): EnviatodoOrderManager {
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->method('create')->willReturn(OrderCreated::create($createdData));
        $balanceApi = $this->createMock(BalanceApi::class);
        $balanceApi->method('show')->willReturn(Balance::create(['balance' => $balance]));
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn($quote);
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willReturn(\SarissaOps\Enviatodo\Model\ZipCode\ZipCode::create([
            'component' => 'zip', 'type' => 'list', 'items' => [[
                'zip_code' => '06600', 'suburb_name' => 'Juárez', 'municipality' => 'Cuauhtémoc',
                'city' => 'Ciudad de México', 'state' => 'Ciudad de México', 'state_code' => 'DF',
            ]],
        ]));
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        return new EnviatodoOrderManager(
            $orderApi,
            $balanceApi,
            new EnviatodoCalculator($quoteApi, $zipCodeApi),
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
            $registry,
            new NullLogger(),
            false,
        );
    }

    /** @return array<string, mixed> */
    private function quoteData(string $uuid = 'uuid-live'): array
    {
        return [
            'transaction' => ['type' => 'order', 'timestamp' => '2026-01-01', 'uuid' => $uuid],
            'packages' => ['quantity' => 1],
            'rates' => [[
                'provider_id' => '9', 'provider_service_id' => '11', 'provider_name' => 'ESTAFETA',
                'service_name' => 'Aéreo', 'status' => true,
                'charges' => [['type' => 'base', 'sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00']],
                'detail_charges' => [['currency' => 'MXN']],
            ]],
        ];
    }

    public function testPurchaseForPaidShipmentBuysWithAFreshQuote(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager->method('getRepository')->willReturn($repository);
        $manager = $this->purchaseManager($entityManager, Quote::create($this->quoteData()), 1000.0, $this->createdData());
        $entityManager->expects($this->once())->method('persist');

        $manager->purchaseForPaidShipment($this->paidShipment(), $this->quoteConfig());

        $this->addToAssertionCount(1);
    }

    public function testPurchaseForPaidShipmentSkipsCompletedDrafts(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $draft = new EnviatodoShipment($this->shipment(), 'uuid-old', '9', '11');
        $draft->complete('TRK0', 100);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn($draft);
        $entityManager->method('getRepository')->willReturn($repository);
        $manager = $this->purchaseManager($entityManager, Quote::create($this->quoteData()), 1000.0, $this->createdData());

        $manager->purchaseForPaidShipment($this->paidShipment(), $this->quoteConfig());

        $this->assertSame('TRK0', $draft->getTrackingId());
    }

    public function testPurchaseForPaidShipmentRefusesInsufficientBalance(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager->method('getRepository')->willReturn($repository);
        $manager = $this->purchaseManager($entityManager, Quote::create($this->quoteData()), 5.0, $this->createdData());
        $entityManager->expects($this->never())->method('persist');

        $this->expectException(EnviatodoOrderException::class);

        $manager->purchaseForPaidShipment($this->paidShipment(), $this->quoteConfig());
    }

    public function testPurchaseForPaidShipmentRejectsEmptyTracking(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager->method('getRepository')->willReturn($repository);
        $manager = $this->purchaseManager($entityManager, Quote::create($this->quoteData()), 1000.0, [
            'summary' => ['sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00'],
            'guides' => [['id' => 'g1', 'insured' => false, 'tracking_id' => '', 'provider_id' => '9']],
        ]);

        $this->expectException(EnviatodoOrderException::class);

        $manager->purchaseForPaidShipment($this->paidShipment(), $this->quoteConfig());
    }
}
