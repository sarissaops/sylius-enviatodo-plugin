<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoMethodEligibilityChecker;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoShippingMethodResetter;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;

final class EnviatodoShippingMethodResetterTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fullQuote(): array
    {
        return ['quote' => [
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
        ]];
    }

    private function orderWithShipment(string $methodCode, string $postcode): OrderInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getPostcode')->willReturn($postcode);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address);
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($methodCode);
        $method->method('getConfiguration')->willReturn($this->fullQuote());
        $current = $method;
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturnCallback(static function () use (&$current): ?ShippingMethodInterface {
            return $current;
        });
        $shipment->method('setMethod')->willReturnCallback(static function (?ShippingMethodInterface $next) use (&$current): void {
            $current = $next;
        });
        $shipment->method('getOrder')->willReturn($order);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        return $order;
    }

    private function resetter(?array $settlementItems): EnviatodoShippingMethodResetter
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willReturn(ZipCode::create(
            ['component' => 'zip', 'type' => 'list', 'items' => $settlementItems ?? []],
        ));

        return new EnviatodoShippingMethodResetter(
            new EnviatodoMethodEligibilityChecker(
                new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
                $zipCodeApi,
            ),
        );
    }

    public function testKeepsQuotableMethods(): void
    {
        $order = $this->orderWithMethodKept();
        $this->resetter([[ 'zip_code' => '06600', 'suburb_name' => 'Juárez', 'municipality' => 'Cuauhtémoc',
            'city' => 'Ciudad de México', 'state' => 'Ciudad de México', 'state_code' => 'DF',
        ]])->process($order);

        /** @var ShipmentInterface $shipment */
        $shipment = $order->getShipments()->first();
        $this->assertNotNull($shipment->getMethod());
    }

    private function orderWithMethodKept(): OrderInterface
    {
        return $this->orderWithShipment('enviatodo_estafeta', '06600');
    }

    public function testUnsetsUnquotableMethods(): void
    {
        $order = $this->orderWithShipment('enviatodo_estafeta', '90210');
        $this->resetter(null)->process($order);

        /** @var ShipmentInterface $shipment */
        $shipment = $order->getShipments()->first();
        $this->assertNull($shipment->getMethod());
    }

    public function testIgnoresMethodsOutsideTheMap(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->expects($this->never())->method('show');
        $checker = new EnviatodoMethodEligibilityChecker(
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
            $zipCodeApi,
        );
        $order = $this->orderWithShipment('flat_rate', '90210');
        (new EnviatodoShippingMethodResetter($checker))->process($order);

        /** @var ShipmentInterface $shipment */
        $shipment = $order->getShipments()->first();
        $this->assertNotNull($shipment->getMethod());
    }
}
