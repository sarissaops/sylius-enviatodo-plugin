<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoMethodEligibilityChecker;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Model\ShippingSubjectInterface;

final class EnviatodoMethodEligibilityCheckerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fullQuote(): array
    {
        return ['quote' => [
            'shipping_type' => '1',
            'quantity' => 1,
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

    /** @return array<string, mixed> */
    private function settlement(): array
    {
        return ['component' => 'zip', 'type' => 'list', 'items' => [[
            'zip_code' => '06600', 'suburb_name' => 'Juárez', 'municipality' => 'Cuauhtémoc',
            'city' => 'Ciudad de México', 'state' => 'Ciudad de México', 'state_code' => 'DF',
        ]]];
    }

    private function subject(?string $postcode): ShippingSubjectInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getPostcode')->willReturn($postcode);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($postcode === null ? null : $address);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }

    private function method(string $code, ?array $configuration): ShippingMethodInterface
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($code);
        $method->method('getConfiguration')->willReturn($configuration ?? []);

        return $method;
    }

    private function checker(ZipCodeApi $zipCodeApi): EnviatodoMethodEligibilityChecker
    {
        return new EnviatodoMethodEligibilityChecker(
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
            $zipCodeApi,
        );
    }

    private function zipCodeApi(?array $items): ZipCodeApi
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willReturn(ZipCode::create(
            ['component' => 'zip', 'type' => 'list', 'items' => $items ?? []],
        ));

        return $zipCodeApi;
    }

    public function testAbstainsFromMethodsOutsideTheMap(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->expects($this->never())->method('show');

        $this->assertTrue($this->checker($zipCodeApi)->isEligible($this->subject('06600'), $this->method('flat_rate', null)));
    }

    public function testEligibleWithFullConfigAndSettlement(): void
    {
        $this->assertTrue(
            $this->checker($this->zipCodeApi($this->settlement()['items']))->isEligible(
                $this->subject('06600'),
                $this->method('enviatodo_estafeta', $this->fullQuote()),
            ),
        );
    }

    public function testHidesMisconfiguredOrigin(): void
    {
        $configuration = $this->fullQuote();
        $configuration['quote']['origin'] = ['zip_code' => '64000'];

        $this->assertFalse(
            $this->checker($this->zipCodeApi($this->settlement()['items']))->isEligible(
                $this->subject('06600'),
                $this->method('enviatodo_estafeta', $configuration),
            ),
        );
    }

    public function testHidesUnknownSettlement(): void
    {
        $this->assertFalse(
            $this->checker($this->zipCodeApi(null))->isEligible(
                $this->subject('90210'),
                $this->method('enviatodo_estafeta', $this->fullQuote()),
            ),
        );
    }

    public function testHidesMissingDestination(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->expects($this->never())->method('show');

        $this->assertFalse(
            $this->checker($zipCodeApi)->isEligible(
                $this->subject(null),
                $this->method('enviatodo_estafeta', $this->fullQuote()),
            ),
        );
    }

    public function testHidesOnTransportFailure(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willThrowException(new \RuntimeException('sandbox down'));

        $this->assertFalse(
            $this->checker($zipCodeApi)->isEligible(
                $this->subject('06600'),
                $this->method('enviatodo_estafeta', $this->fullQuote()),
            ),
        );
    }
}
