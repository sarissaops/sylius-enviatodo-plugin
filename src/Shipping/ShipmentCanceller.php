<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Api\OrderApi;

/**
 * Label cancellation. The refund lands ~7 days later, so callers surface the
 * returned window as *pending* — never as refunded.
 */
final class ShipmentCanceller
{
    public function __construct(
        private readonly OrderApi $orderApi,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $trackingIds
     *
     * @return array{cancelled_at: string, refunded_at: string}
     */
    public function cancel(array $trackingIds, string $reasonValue, string $reasonText): array
    {
        if ([] === $trackingIds) {
            throw new \InvalidArgumentException('Cancel requires at least one tracking id.');
        }

        $cancelledAt = new \DateTimeImmutable();
        $refundedAt = $cancelledAt->modify('+7 days');

        $this->orderApi->cancel(
            $trackingIds,
            $cancelledAt->format('Y-m-d H:i:s'),
            $refundedAt->format('Y-m-d H:i:s'),
            $reasonValue,
            $reasonText,
            'rates',
        );

        $this->logger->info('Enviatodo labels cancelled; refund pending.', ['tracking_ids' => $trackingIds]);

        return [
            'cancelled_at' => $cancelledAt->format('Y-m-d H:i:s'),
            'refunded_at' => $refundedAt->format('Y-m-d H:i:s'),
        ];
    }
}
