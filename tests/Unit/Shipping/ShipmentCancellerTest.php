<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Order\CancelResponse;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\ShipmentCanceller;

final class ShipmentCancellerTest extends TestCase
{
    public function testCancelReturnsThePendingWindow(): void
    {
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->expects($this->once())->method('cancel')->with(
            ['TRK1'],
            $this->isType('string'),
            $this->isType('string'),
            '7',
            'customer request',
            'rates',
        )->willReturn(CancelResponse::create([]));

        $result = (new ShipmentCanceller($orderApi, new NullLogger()))->cancel(['TRK1'], '7', 'customer request');

        $this->assertArrayHasKey('cancelled_at', $result);
        $this->assertArrayHasKey('refunded_at', $result);
        $this->assertGreaterThan($result['cancelled_at'], $result['refunded_at']);
    }

    public function testCancelRejectsEmptyTrackingLists(): void
    {
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->expects($this->never())->method('cancel');

        $this->expectException(\InvalidArgumentException::class);

        (new ShipmentCanceller($orderApi, new NullLogger()))->cancel([], '7', 'customer request');
    }
}
