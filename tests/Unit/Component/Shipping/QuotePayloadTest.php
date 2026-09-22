<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;

final class QuotePayloadTest extends TestCase
{
    /** @return array<string, mixed> */
    private function package(): array
    {
        return [
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
        ];
    }

    public function testCreateBuildsTheQuotesEnvelope(): void
    {
        $payload = QuotePayload::create(['zip_code' => '64000'], ['zip_code' => '06600'], $this->package(), 2, '1');

        $this->assertSame('1', $payload['shipping_type']);
        $this->assertSame(2, $payload['quantity']);
        $this->assertSame(['zip_code' => '64000'], $payload['origin']);
        $this->assertSame(['zip_code' => '06600'], $payload['destination']);
        $this->assertSame($this->package(), $payload['package']);
    }

    public function testCreateRejectsPackagesMissingWeightKeys(): void
    {
        $package = $this->package();
        unset($package['bill_weight']);

        $this->expectException(\InvalidArgumentException::class);

        QuotePayload::create(['zip_code' => '64000'], ['zip_code' => '06600'], $package);
    }

    public function testCreateRejectsEmptyAddresses(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        QuotePayload::create([], ['zip_code' => '06600'], $this->package());
    }
}
