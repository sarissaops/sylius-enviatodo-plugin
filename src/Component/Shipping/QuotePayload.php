<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

/**
 * Builds the inner `quotes` payload for rates_client. Rejects locally what the
 * API would answer with HTTP 200 + error:true (missing weight keys), so no
 * useless traffic is produced.
 */
final class QuotePayload
{
    /** @var list<string> int_number is optional (empty accepted by the API). */
    public const REQUIRED_ORIGIN_KEYS = [
        'address_type_id',
        'full_name',
        'email',
        'telephone',
        'street',
        'ext_number',
        'zip_code',
        'suburb',
        'municipality',
        'town',
        'state',
        'state_code',
        'country_code',
    ];

    /** @var list<string> */
    public const REQUIRED_PACKAGE_KEYS = [
        'product_type',
        'unit_type',
        'package_content',
        'amount_pkg',
        'height',
        'width',
        'length',
        'weight',
        'real_weight',
        'volumetric_weight',
        'bill_weight',
        'default_pkg',
    ];

    /**
     * @param array<string, mixed> $origin
     * @param array<string, mixed> $destination
     * @param array<string, mixed> $package
     *
     * @return array<string, mixed>
     */
    public static function create(
        array $origin,
        array $destination,
        array $package,
        int $quantity = 1,
        string $shippingType = '1',
    ): array {
        if ([] === $origin) {
            throw new \InvalidArgumentException('Quote requires a non-empty origin address.');
        }
        if ([] === $destination) {
            throw new \InvalidArgumentException('Quote requires a non-empty destination address.');
        }
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quote quantity must be positive.');
        }
        foreach (self::REQUIRED_PACKAGE_KEYS as $key) {
            if (!\array_key_exists($key, $package)) {
                throw new \InvalidArgumentException(sprintf('Quote package requires key "%s".', $key));
            }
        }

        return [
            'shipping_type' => $shippingType,
            'quantity' => $quantity,
            'origin' => $origin,
            'destination' => $destination,
            'package' => $package,
        ];
    }

    /**
     * Drops non-string keys from decoded JSON and form data, where numeric
     * keys carry no address meaning. Single normalization point for every
     * payload entering the calculator.
     *
     * @param array<mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Names the required keys absent from the given block, so misconfigured
     * method payloads fail with the missing field names instead of an
     * opaque API rejection.
     *
     * @param array<mixed> $block
     * @param list<string> $required
     *
     * @return list<string>
     */
    public static function missingKeys(array $block, array $required): array
    {
        $missing = [];
        foreach ($required as $key) {
            $value = $block[$key] ?? null;
            if (!\is_scalar($value) || '' === trim((string) $value)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
