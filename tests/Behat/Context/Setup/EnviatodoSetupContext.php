<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ManagerRegistry;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Webmozart\Assert\Assert;

final class EnviatodoSetupContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        /** @var ExampleFactoryInterface<ShippingMethodInterface> */
        private ExampleFactoryInterface $shippingMethodFactory,
        /** @var OrderRepositoryInterface<OrderInterface> */
        private OrderRepositoryInterface $orderRepository,
        private ManagerRegistry $registry,
    ) {
    }

    /**
     * @Given the store allows Enviatodo shipping method :name with code :code
     */
    public function allowEnviatodoMethod(string $name, string $code): void
    {
        $channel = $this->sharedStorage->get('channel');

        $method = $this->shippingMethodFactory->create([
            'name' => $name,
            'code' => $code,
            'enabled' => true,
            'channels' => [$channel],
            'calculator' => ['type' => 'enviatodo', 'configuration' => ['quote' => $this->quotePayload()]],
        ]);

        $this->sharedStorage->set('shipping_method', $method);
    }

    /**
     * @Given the shipment of the latest order has Enviatodo tracking :tracking
     */
    public function latestOrderShipmentHasTracking(string $tracking): void
    {
        $orders = $this->orderRepository->findBy([], ['createdAt' => 'DESC'], 1);
        $order = $orders[0] ?? null;
        Assert::isInstanceOf($order, OrderInterface::class);

        $shipment = $order->getShipments()->first();
        Assert::isInstanceOf($shipment, ShipmentInterface::class);

        $record = new EnviatodoShipment($shipment, 'uuid-behat-1', '9', '11');
        $record->complete($tracking, 23200);

        $manager = $this->registry->getManager();
        $manager->persist($record);
        $manager->flush();
    }

    /** @return array<string, mixed> */
    private function quotePayload(): array
    {
        return [
            'shipping_type' => '1',
            'quantity' => 1,
            // Warehouse origin: full address block, exactly what rates_client requires.
            'origin' => [
                'address_type_id' => '1',
                'full_name' => 'Tienda Norte',
                'email' => 'tienda@example.com',
                'telephone' => '8180808080',
                'street' => 'JUAN IGNACIO RAMON',
                'ext_number' => '321',
                'int_number' => '1A',
                'zip_code' => '64000',
                'suburb' => 'Monterrey Centro',
                'municipality' => 'Monterrey',
                'town' => 'Monterrey',
                'state' => 'Nuevo Leon',
                'state_code' => 'NL',
                'country_code' => 'MX',
            ],
            // Shop contact phone: checkout collects no phone, used as destination telephone fallback.
            'contact' => ['telephone' => '8180808080'],
            'package' => [
                'product_type' => '01010101',
                'unit_type' => 'X1A',
                'package_content' => 'books',
                'amount_pkg' => '200',
                'height' => 10,
                'width' => 10,
                'length' => 10,
                'weight' => 1,
                'real_weight' => '1.00',
                'volumetric_weight' => '1',
                'bill_weight' => '1',
                'default_pkg' => '0',
            ],
        ];
    }
}
