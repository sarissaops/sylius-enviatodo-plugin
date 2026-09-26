<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;

final class ProviderMapTest extends TestCase
{
    public function testBackedMethodResolvesItsProviderAndService(): void
    {
        $map = new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]);

        $this->assertTrue($map->isBackedByEnviatodo('enviatodo_estafeta'));
        $this->assertFalse($map->isBackedByEnviatodo('flat_rate'));
        $this->assertSame(
            ['provider_id' => '9', 'provider_service_id' => '11'],
            $map->getProviderService('enviatodo_estafeta'),
        );
        $this->assertNull($map->getProviderService('flat_rate'));
    }

    public function testMalformedRowsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9']]);
    }

    public function testNonArrayRowsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProviderMap(['enviatodo_estafeta' => '9']);
    }

    public function testNonNumericIdsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProviderMap(['enviatodo_estafeta' => ['provider_id' => 'EXPRESS', 'provider_service_id' => '11']]);
    }
}
