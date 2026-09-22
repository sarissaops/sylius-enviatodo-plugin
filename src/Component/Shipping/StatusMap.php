<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

/**
 * Enviatodo status codes to local handover keys. Unknown codes map to
 * 'unknown' so synchronization never breaks on new carrier states.
 */
final class StatusMap
{
    /** @var array<int, string> */
    private const ORDER = [
        41 => 'label_created',
        42 => 'in_transit',
        43 => 'cancelled',
        44 => 'delivered',
        59 => 'in_pickup',
        68 => 'in_return',
        69 => 'incident',
    ];

    /** @var array<int, string> */
    private const PICKUP = [
        60 => 'pickup_pending',
        61 => 'pickup_created',
        62 => 'pickup_cancelled',
        71 => 'pickup_rescheduled',
    ];

    public function forOrder(int $status): string
    {
        return self::ORDER[$status] ?? 'unknown';
    }

    public function forPickup(int $status): string
    {
        return self::PICKUP[$status] ?? 'unknown';
    }
}
