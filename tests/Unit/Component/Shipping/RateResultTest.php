<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Model\Quote\RateRow;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\RateResult;

final class RateResultTest extends TestCase
{
    public function testFromRateRowConvertsPesosToCentavos(): void
    {
        $row = RateRow::create([
            'provider_id' => '9',
            'provider_service_id' => '11',
            'provider_name' => 'ESTAFETA',
            'service_name' => 'Aéreo',
            'estimated_date' => '2026-01-03',
            'charges' => [
                ['type' => 'base', 'sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00'],
                ['type' => 'extra', 'sub_total' => '10.00', 'tax' => '1.60', 'total' => '11.60'],
            ],
            'detail_charges' => [['currency' => 'MXN']],
        ]);

        $result = RateResult::fromRateRow($row);

        $this->assertSame('9', $result->getProviderId());
        $this->assertSame('11', $result->getProviderServiceId());
        $this->assertSame('ESTAFETA', $result->getProviderName());
        $this->assertSame('Aéreo', $result->getServiceName());
        $this->assertSame(24360, $result->getAmountMinor());
        $this->assertSame('MXN', $result->getCurrency());
        $this->assertSame('2026-01-03', $result->getEstimatedDate());
    }

    public function testFromRateRowDefaultsCurrencyToMxn(): void
    {
        $row = RateRow::create([
            'provider_id' => '9',
            'provider_service_id' => '11',
            'charges' => [['type' => 'base', 'sub_total' => '100', 'tax' => '16', 'total' => '116']],
        ]);

        $this->assertSame('MXN', RateResult::fromRateRow($row)->getCurrency());
    }
}
