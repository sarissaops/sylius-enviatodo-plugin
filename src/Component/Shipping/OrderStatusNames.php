<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

/**
 * The order-history API reports statuses as localized display names
 * ("GUÍA GENERADA"), never the numeric catalog codes. This normalizer maps
 * the known names back to codes (accent/case/whitespace-insensitive);
 * unknown names return null so callers log-and-skip instead of misrouting.
 */
final class OrderStatusNames
{
    /** @var array<string, int> */
    private const BY_NORMALIZED_NAME = [
        'guia generada' => 41,
        'guia en transito' => 42,
        'guia cancelada' => 43,
        'guia entregada' => 44,
        'recoleccion' => 59,
        'recoleccion pendiente' => 60,
        'recoleccion generada' => 61,
        'recoleccion cancelada' => 62,
        'guia en devolucion' => 68,
        'incidencia en guia' => 69,
        'recoleccion reprogramada' => 71,
    ];

    public static function toCode(string $statusName): ?int
    {
        $normalized = trim($statusName);
        if ('' === $normalized) {
            return null;
        }
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $normalized = strtolower(\is_string($transliterated) ? $transliterated : $normalized);

        return self::BY_NORMALIZED_NAME[$normalized] ?? null;
    }
}
