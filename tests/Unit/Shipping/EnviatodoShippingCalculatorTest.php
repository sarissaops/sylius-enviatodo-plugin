<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\NoRateAvailableException;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoShippingCalculator;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;

final class EnviatodoShippingCalculatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function quoteData(): array
    {
        return [
            'transaction' => ['type' => 'order', 'timestamp' => '2026-01-01', 'uuid' => 'uuid-1'],
            'packages' => ['quantity' => 1],
            'rates' => [
                [
                    'provider_id' => '9',
                    'provider_service_id' => '11',
                    'provider_name' => 'ESTAFETA',
                    'service_name' => 'Aéreo',
                    'status' => true,
                    'charges' => [['type' => 'base', 'sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00']],
                    'detail_charges' => [['currency' => 'MXN']],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function settlement(): array
    {
        return [
            'component' => 'zip',
            'type' => 'list',
            'items' => [[
                'zip_code' => '06600',
                'suburb_name' => 'Juárez',
                'municipality' => 'Cuauhtémoc',
                'city' => 'Ciudad de México',
                'state' => 'Ciudad de México',
                'state_code' => 'DF',
            ]],
        ];
    }

    private function address(?string $phoneNumber = '5555555555'): AddressInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('John');
        $address->method('getLastName')->willReturn('Doe');
        $address->method('getPhoneNumber')->willReturn($phoneNumber);
        $address->method('getStreet')->willReturn('Paseo Violetas 10');
        $address->method('getPostcode')->willReturn('06600');
        $address->method('getCountryCode')->willReturn('MX');

        return $address;
    }

    private function shipment(string $methodCode, ?AddressInterface $address = null): ShipmentInterface
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('john@example.com');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address ?? $this->address());
        $order->method('getCustomer')->willReturn($customer);

        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($methodCode);

        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($method);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return ['quote' => [
            'shipping_type' => '1',
            'quantity' => 1,
            'origin' => ['zip_code' => '64000'],
            'package' => ['bill_weight' => '1'],
        ]];
    }

    private function calculator(QuoteApi $quoteApi, ZipCodeApi $zipCodeApi): EnviatodoShippingCalculator
    {
        return new EnviatodoShippingCalculator(
            new EnviatodoCalculator($quoteApi, $zipCodeApi),
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );
    }

    private function zipCodeApi(): ZipCodeApi
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willReturn(ZipCode::create($this->settlement()));

        return $zipCodeApi;
    }

    public function testGetTypeIsEnviatodo(): void
    {
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($this->createMock(QuoteApi::class), $this->createMock(ZipCodeApi::class)),
            new ProviderMap([]),
        );

        $this->assertSame('enviatodo', $calculator->getType());
    }

    public function testCalculateReturnsMatchedRateInCentavos(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create($this->quoteData()));

        $this->assertSame(23200, $this->calculator($quoteApi, $this->zipCodeApi())->calculate($this->shipment('enviatodo_estafeta'), $this->configuration()));
    }

    public function testCalculateThrowsWhenNoRateMatches(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create(['transaction' => [], 'packages' => [], 'rates' => []]));

        $this->expectException(NoRateAvailableException::class);

        $this->calculator($quoteApi, $this->zipCodeApi())->calculate($this->shipment('enviatodo_estafeta'), $this->configuration());
    }

    public function testCalculateFallsBackToConfiguredContactTelephone(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->once())->method('byService')->with(
            $this->callback(static fn (array $payload): bool => '5544332211' === ($payload['destination']['telephone'] ?? null)),
        )->willReturn(Quote::create($this->quoteData()));

        $address = $this->address(null);
        $configuration = $this->configuration();
        $configuration['quote']['contact'] = ['telephone' => '5544332211'];

        $this->assertSame(23200, $this->calculator($quoteApi, $this->zipCodeApi())->calculate($this->shipment('enviatodo_estafeta', $address), $configuration));
    }

    public function testCalculateThrowsWithoutSettlement(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->never())->method('byService');
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willReturn(ZipCode::create(['component' => 'zip', 'type' => 'list', 'items' => []]));

        $this->expectException(NoRateAvailableException::class);

        $this->calculator($quoteApi, $zipCodeApi)->calculate($this->shipment('enviatodo_estafeta'), $this->configuration());
    }

    public function testCalculateRejectsMethodsOutsideTheMap(): void
    {
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($this->createMock(QuoteApi::class), $this->createMock(ZipCodeApi::class)),
            new ProviderMap([]),
        );

        $this->expectException(\LogicException::class);

        $calculator->calculate($this->shipment('flat_rate'), $this->configuration());
    }

    public function testCalculateRejectsMissingQuoteConfiguration(): void
    {
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($this->createMock(QuoteApi::class), $this->createMock(ZipCodeApi::class)),
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );

        $this->expectException(\InvalidArgumentException::class);

        $calculator->calculate($this->shipment('enviatodo_estafeta'), []);
    }
}
