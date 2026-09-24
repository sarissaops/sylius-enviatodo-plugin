<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

/**
 * Builds the `destination` block for `rates_client` from a checkout contact
 * plus one settlement row from the zip-code catalog. Framework-free: callers
 * pass primitives, never Sylius objects.
 */
final class DestinationAddress
{
    /**
     * @param array{fullName: string, email: string, telephone: string, street: string, postcode: string, countryCode: string} $contact
     * @param array<string, mixed> $settlement
     *
     * @return array<string, mixed>
     */
    public static function fromParts(array $contact, array $settlement): array
    {
        $fields = [
            'fullName' => $contact['fullName'],
            'email' => $contact['email'],
            'telephone' => $contact['telephone'],
            'street' => $contact['street'],
            'postcode' => $contact['postcode'],
            'countryCode' => $contact['countryCode'],
        ];
        foreach ($fields as $key => $value) {
            if ('' === trim($value)) {
                throw new \InvalidArgumentException(sprintf('Destination contact requires non-empty "%s".', $key));
            }
        }

        $street = trim($contact['street']);

        return [
            'address_type_id' => '2',
            'full_name' => trim($contact['fullName']),
            'email' => trim($contact['email']),
            'telephone' => trim($contact['telephone']),
            'street' => $street,
            'ext_number' => self::exteriorNumber($street),
            'int_number' => '',
            'zip_code' => trim($contact['postcode']),
            'suburb' => self::text($settlement, 'suburb_name', 'Destination settlement'),
            'municipality' => self::text($settlement, 'municipality', 'Destination settlement'),
            'town' => self::text($settlement, 'city', 'Destination settlement'),
            'state' => self::text($settlement, 'state', 'Destination settlement'),
            'state_code' => self::text($settlement, 'state_code', 'Destination settlement'),
            'country_code' => strtoupper(trim($contact['countryCode'])),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function text(array $row, string $key, string $what): string
    {
        $value = $row[$key] ?? null;
        if (!\is_scalar($value) || '' === trim((string) $value)) {
            throw new \InvalidArgumentException(sprintf('%s requires non-empty "%s".', $what, $key));
        }

        return trim((string) $value);
    }

    private static function exteriorNumber(string $street): string
    {
        if (1 === preg_match('/(\d[\d\-\/]*)\s*$/', $street, $matches)) {
            return $matches[1];
        }

        return 'S/N';
    }
}
