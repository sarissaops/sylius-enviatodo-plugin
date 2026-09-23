<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\AddressApi;
use SarissaOps\Enviatodo\Api\PackageApi;
use SarissaOps\Enviatodo\Model\Address\Address;
use SarissaOps\Enviatodo\Model\Package\Package;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoAddressBook;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoCheckoutDefaults;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPackageCatalog;

final class EnviatodoCheckoutDefaultsTest extends TestCase
{
    private function defaults(?int $originId, ?int $packageId): EnviatodoCheckoutDefaults
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->method('show')->willReturnCallback(
            static fn (int $id): array => 12 === $id ? [Address::create(['id' => '12', 'address_type_id' => '1', 'full_name' => 'HQ', 'zip_code' => '64000'])] : [],
        );
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->method('show')->willReturnCallback(
            static fn (int $id): array => 5 === $id ? [Package::create(['id' => '5', 'name' => 'Box', 'default_pkg' => '0'])] : [],
        );

        return new EnviatodoCheckoutDefaults(
            new EnviatodoAddressBook($addressApi, new NullLogger()),
            new EnviatodoPackageCatalog($packageApi, new NullLogger()),
            $originId,
            $packageId,
        );
    }

    public function testResolvesConfiguredDefaults(): void
    {
        $defaults = $this->defaults(12, 5);

        $origin = $defaults->originAddress();
        $this->assertNotNull($origin);
        $this->assertSame('12', $origin->getId());

        $package = $defaults->defaultPackage();
        $this->assertNotNull($package);
        $this->assertSame('Box', $package->getName());
    }

    public function testMissingConfigurationResolvesToNull(): void
    {
        $defaults = $this->defaults(null, null);

        $this->assertNull($defaults->originAddress());
        $this->assertNull($defaults->defaultPackage());
    }

    public function testUnknownIdsResolveToNull(): void
    {
        $defaults = $this->defaults(999, 999);

        $this->assertNull($defaults->originAddress());
        $this->assertNull($defaults->defaultPackage());
    }
}
