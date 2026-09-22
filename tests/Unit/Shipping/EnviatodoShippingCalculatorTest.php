<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoShippingCalculator;
use Sylius\Component\Shipping\Model\ShipmentInterface;
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

    private function shipment(string $methodCode): ShipmentInterface
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($methodCode);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($method);

        return $shipment;
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return ['quote' => ['shipping_type' => '1', 'quantity' => 1, 'origin' => [], 'destination' => [], 'package' => []]];
    }

    public function testGetTypeIsEnviatodo(): void
    {
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($this->createMock(QuoteApi::class)),
            new ProviderMap([]),
        );

        $this->assertSame('enviatodo', $calculator->getType());
    }

    public function testCalculateReturnsMatchedRateInCentavos(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create($this->quoteData()));
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($quoteApi),
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );

        $this->assertSame(23200, $calculator->calculate($this->shipment('enviatodo_estafeta'), $this->configuration()));
    }

    public function testCalculateReturnsZeroWhenNoRateMatches(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create(['transaction' => [], 'packages' => [], 'rates' => []]));
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($quoteApi),
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );

        $this->assertSame(0, $calculator->calculate($this->shipment('enviatodo_estafeta'), $this->configuration()));
    }

    public function testCalculateRejectsMethodsOutsideTheMap(): void
    {
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($this->createMock(QuoteApi::class)),
            new ProviderMap([]),
        );

        $this->expectException(\LogicException::class);

        $calculator->calculate($this->shipment('flat_rate'), $this->configuration());
    }

    public function testCalculateRejectsMissingQuoteConfiguration(): void
    {
        $calculator = new EnviatodoShippingCalculator(
            new EnviatodoCalculator($this->createMock(QuoteApi::class)),
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );

        $this->expectException(\InvalidArgumentException::class);

        $calculator->calculate($this->shipment('enviatodo_estafeta'), []);
    }
}
