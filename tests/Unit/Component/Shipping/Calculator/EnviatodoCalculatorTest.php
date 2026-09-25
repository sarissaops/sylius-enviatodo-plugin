<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping\Calculator;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;

final class EnviatodoCalculatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function quotePayload(): array
    {
        return [
            'shipping_type' => '1',
            'quantity' => 1,
            'origin' => ['zip_code' => '64000'],
            'destination' => ['zip_code' => '06600'],
            'package' => ['bill_weight' => '1'],
        ];
    }

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
                    'priority' => 1,
                    'estimated_date' => '2026-01-03',
                    'charges' => [['type' => 'base', 'sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00']],
                    'detail_charges' => [['currency' => 'MXN']],
                    'offer' => [],
                ],
                [
                    'provider_id' => '5',
                    'provider_service_id' => '6',
                    'provider_name' => 'DHL',
                    'service_name' => 'Aéreo',
                    'status' => false,
                    'priority' => 2,
                    'estimated_date' => '2026-01-02',
                    'charges' => [['type' => 'base', 'sub_total' => '300.00', 'tax' => '48.00', 'total' => '348.00']],
                    'detail_charges' => [['currency' => 'MXN']],
                    'offer' => [],
                ],
            ],
        ];
    }

    public function testRatesQueriesAllCarriersAndKeepsOnlyAvailableRows(): void
    {
        $payload = $this->quotePayload();
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->once())->method('all')->with($payload)->willReturn(Quote::create($this->quoteData()));

        $result = (new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class)))->rates($payload);

        $this->assertSame('uuid-1', $result->getTransactionUuid());
        $rates = $result->getRates();
        $this->assertCount(1, $rates);
        $this->assertSame('9', $rates[0]->getProviderId());
        $this->assertSame('11', $rates[0]->getProviderServiceId());
        $this->assertSame(23200, $rates[0]->getAmountMinor());
        $this->assertSame('MXN', $rates[0]->getCurrency());
    }

    public function testRatesQueriesSingleProviderWhenProviderIdGiven(): void
    {
        $payload = $this->quotePayload();
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->once())->method('byProvider')->with($payload, 9)->willReturn(Quote::create($this->quoteData()));
        $quoteApi->expects($this->never())->method('all');

        $result = (new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class)))->rates($payload, '9');

        $this->assertCount(1, $result->getRates());
    }

    public function testRatesQueriesSingleServiceWhenServiceIdGiven(): void
    {
        $payload = $this->quotePayload();
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->once())->method('byService')->with($payload, 11)->willReturn(Quote::create($this->quoteData()));

        $result = (new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class)))->rates($payload, null, '11');

        $this->assertCount(1, $result->getRates());
    }

    public function testRatesThrowsWhenHydrationIsNotAModel(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('all')->willReturn([]);

        $this->expectException(\RuntimeException::class);

        (new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class)))->rates($this->quotePayload());
    }

    public function testRateForReturnsMatchingAvailableService(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create($this->quoteData()));

        $rate = (new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class)))->rateFor($this->quotePayload(), '9', '11');

        $this->assertNotNull($rate);
        $this->assertSame(23200, $rate->getAmountMinor());
    }

    public function testRateForReturnsNullWhenServiceIsAbsentOrUnavailable(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create($this->quoteData()));

        $calculator = new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class));

        $this->assertNull($calculator->rateFor($this->quotePayload(), '9', '12'));
        $this->assertNull($calculator->rateFor($this->quotePayload(), '5', '6'));
    }

    /** @return array<string, mixed> */
    private function fullOrigin(): array
    {
        return [
            'address_type_id' => '1', 'full_name' => 'Tienda', 'email' => 't@example.com',
            'telephone' => '8180808080', 'street' => 'Street 1', 'ext_number' => '1', 'int_number' => '',
            'zip_code' => '64000', 'suburb' => 'Centro', 'municipality' => 'Monterrey', 'town' => 'Monterrey',
            'state' => 'Nuevo Leon', 'state_code' => 'NL', 'country_code' => 'MX',
        ];
    }

    /** @return array<string, mixed> */
    private function fullPackage(): array
    {
        return [
            'product_type' => '01010101', 'unit_type' => 'X1A', 'package_content' => 'books',
            'amount_pkg' => '200', 'height' => 10, 'width' => 10, 'length' => 10, 'weight' => 1,
            'real_weight' => '1.00', 'volumetric_weight' => '1', 'bill_weight' => '1', 'default_pkg' => '0',
        ];
    }

    public function testRateForAddressResolvesSettlementAndMatchesService(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->once())->method('byService')->with(
            $this->callback(static fn (array $payload): bool => 'Juárez' === ($payload['destination']['suburb'] ?? null)
                && '64000' === ($payload['origin']['zip_code'] ?? null)),
            11,
        )->willReturn(Quote::create($this->quoteData()));
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->expects($this->once())->method('show')->with('06600')->willReturn(ZipCode::create([
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
        ]));

        $rate = (new EnviatodoCalculator($quoteApi, $zipCodeApi))->rateForAddress(
            ['origin' => $this->fullOrigin(), 'package' => $this->fullPackage()],
            ['fullName' => 'John Doe', 'email' => 'john@example.com', 'telephone' => '5555555555', 'street' => 'Paseo Violetas 10', 'postcode' => '06600', 'countryCode' => 'mx'],
            '9',
            '11',
        );

        $this->assertNotNull($rate);
        $this->assertSame(23200, $rate->getAmountMinor());
    }

    public function testRateForAddressReturnsNullWithoutSettlement(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->never())->method('byService');
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willReturn(ZipCode::create(['component' => 'zip', 'type' => 'list', 'items' => []]));

        $rate = (new EnviatodoCalculator($quoteApi, $zipCodeApi))->rateForAddress(
            ['origin' => $this->fullOrigin(), 'package' => $this->fullPackage()],
            ['fullName' => 'John Doe', 'email' => 'john@example.com', 'telephone' => '5555555555', 'street' => 'Paseo Violetas 10', 'postcode' => '00000', 'countryCode' => 'MX'],
            '9',
            '11',
        );

        $this->assertNull($rate);
    }

    public function testRateForAddressRejectsConfigWithoutOrigin(): void
    {
        $calculator = new EnviatodoCalculator($this->createMock(QuoteApi::class), $this->createMock(ZipCodeApi::class));

        $this->expectException(\InvalidArgumentException::class);

        $calculator->rateForAddress(
            ['package' => ['bill_weight' => '1']],
            ['fullName' => 'John Doe', 'email' => 'john@example.com', 'telephone' => '5555555555', 'street' => 'Paseo Violetas 10', 'postcode' => '06600', 'countryCode' => 'MX'],
            '9',
            '11',
        );
    }
}
