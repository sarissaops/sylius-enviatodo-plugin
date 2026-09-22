<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping\Calculator;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Model\Quote\Quote;
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

        $result = (new EnviatodoCalculator($quoteApi))->rates($payload);

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

        $result = (new EnviatodoCalculator($quoteApi))->rates($payload, '9');

        $this->assertCount(1, $result->getRates());
    }

    public function testRatesQueriesSingleServiceWhenServiceIdGiven(): void
    {
        $payload = $this->quotePayload();
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->expects($this->once())->method('byService')->with($payload, 11)->willReturn(Quote::create($this->quoteData()));

        $result = (new EnviatodoCalculator($quoteApi))->rates($payload, null, '11');

        $this->assertCount(1, $result->getRates());
    }

    public function testRatesThrowsWhenHydrationIsNotAModel(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('all')->willReturn([]);

        $this->expectException(\RuntimeException::class);

        (new EnviatodoCalculator($quoteApi))->rates($this->quotePayload());
    }

    public function testRateForReturnsMatchingAvailableService(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create($this->quoteData()));

        $rate = (new EnviatodoCalculator($quoteApi))->rateFor($this->quotePayload(), '9', '11');

        $this->assertNotNull($rate);
        $this->assertSame(23200, $rate->getAmountMinor());
    }

    public function testRateForReturnsNullWhenServiceIsAbsentOrUnavailable(): void
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('byService')->willReturn(Quote::create($this->quoteData()));

        $calculator = new EnviatodoCalculator($quoteApi);

        $this->assertNull($calculator->rateFor($this->quotePayload(), '9', '12'));
        $this->assertNull($calculator->rateFor($this->quotePayload(), '5', '6'));
    }
}
