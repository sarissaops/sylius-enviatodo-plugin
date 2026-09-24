<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\DestinationAddress;

final class DestinationAddressTest extends TestCase
{
    /** @return array<string, mixed> */
    private function settlement(): array
    {
        return [
            'zip_code' => '06600',
            'suburb_name' => 'Juárez',
            'municipality' => 'Cuauhtémoc',
            'state' => 'Ciudad de México',
            'city' => 'Ciudad de México',
            'state_code' => 'DF',
        ];
    }

    /** @return array{fullName: string, email: string, telephone: string, street: string, postcode: string, countryCode: string} */
    private function contact(): array
    {
        return [
            'fullName' => 'John Doe',
            'email' => 'john@example.com',
            'telephone' => '5555555555',
            'street' => 'Paseo Violetas 10',
            'postcode' => '06600',
            'countryCode' => 'MX',
        ];
    }

    public function testBuildsFullDestinationBlock(): void
    {
        $destination = DestinationAddress::fromParts($this->contact(), $this->settlement());

        $this->assertSame('2', $destination['address_type_id']);
        $this->assertSame('John Doe', $destination['full_name']);
        $this->assertSame('john@example.com', $destination['email']);
        $this->assertSame('Paseo Violetas 10', $destination['street']);
        $this->assertSame('10', $destination['ext_number']);
        $this->assertSame('06600', $destination['zip_code']);
        $this->assertSame('Juárez', $destination['suburb']);
        $this->assertSame('Cuauhtémoc', $destination['municipality']);
        $this->assertSame('Ciudad de México', $destination['town']);
        $this->assertSame('DF', $destination['state_code']);
        $this->assertSame('MX', $destination['country_code']);
    }

    public function testFallsBackToSinNumeroWithoutStreetDigits(): void
    {
        $contact = $this->contact();
        $contact['street'] = 'Paseo Violetas';

        $destination = DestinationAddress::fromParts($contact, $this->settlement());

        $this->assertSame('S/N', $destination['ext_number']);
    }

    public function testRejectsSettlementWithoutColony(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DestinationAddress::fromParts($this->contact(), ['municipality' => 'Cuauhtémoc']);
    }

    public function testRejectsEmptyContactFields(): void
    {
        $contact = $this->contact();
        $contact['postcode'] = '';

        $this->expectException(\InvalidArgumentException::class);

        DestinationAddress::fromParts($contact, $this->settlement());
    }
}
