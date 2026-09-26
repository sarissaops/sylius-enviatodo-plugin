<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\OrderStatusNames;

final class OrderStatusNamesTest extends TestCase
{
    public function testMapsKnownNamesToCodes(): void
    {
        $this->assertSame(41, OrderStatusNames::toCode('Guía generada'));
        $this->assertSame(42, OrderStatusNames::toCode('Guía en transito'));
        $this->assertSame(43, OrderStatusNames::toCode('Guía cancelada'));
        $this->assertSame(44, OrderStatusNames::toCode('Guía entregada'));
        $this->assertSame(59, OrderStatusNames::toCode('Recolección'));
        $this->assertSame(68, OrderStatusNames::toCode('Guia en devolución'));
        $this->assertSame(69, OrderStatusNames::toCode('Incidencia en guía'));
    }

    public function testNormalizesCaseAccentsAndWhitespace(): void
    {
        $this->assertSame(41, OrderStatusNames::toCode('  guia GENERADA '));
        $this->assertSame(71, OrderStatusNames::toCode('Recolección Reprogramada'));
    }

    public function testReturnsNullForUnknownNames(): void
    {
        $this->assertNull(OrderStatusNames::toCode(''));
        $this->assertNull(OrderStatusNames::toCode('En camino'));
    }
}
