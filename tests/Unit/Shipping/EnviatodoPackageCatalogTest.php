<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\PackageApi;
use SarissaOps\Enviatodo\Model\Package\Package;
use SarissaOps\Enviatodo\Model\Package\SavedPackage;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPackageCatalog;

final class EnviatodoPackageCatalogTest extends TestCase
{
    /** @return array<string, mixed> */
    private function packageData(string $id = '5'): array
    {
        return ['id' => $id, 'name' => 'Box', 'default_pkg' => '0', 'bill_weight' => '1'];
    }

    public function testGetReturnsTheFirstMatch(): void
    {
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->expects($this->once())->method('show')->with(5)->willReturn([Package::create($this->packageData())]);

        $package = (new EnviatodoPackageCatalog($packageApi, new NullLogger()))->get(5);

        $this->assertNotNull($package);
        $this->assertSame('Box', $package->getName());
    }

    public function testGetReturnsNullWhenAbsent(): void
    {
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->method('show')->willReturn([]);

        $this->assertNull((new EnviatodoPackageCatalog($packageApi, new NullLogger()))->get(404));
    }

    public function testSaveReturnsTheStoredPackage(): void
    {
        $data = $this->packageData();
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->expects($this->once())->method('save')->with($data)->willReturn(SavedPackage::create(['package' => $data]));

        $saved = (new EnviatodoPackageCatalog($packageApi, new NullLogger()))->save($data);

        $this->assertSame('5', $saved->getId());
    }

    public function testDefaultPackageFindsTheFlaggedPreset(): void
    {
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->method('all')->willReturn([
            Package::create($this->packageData('5')),
            Package::create(array_merge($this->packageData('6'), ['default_pkg' => '1'])),
        ]);

        $default = (new EnviatodoPackageCatalog($packageApi, new NullLogger()))->defaultPackage();

        $this->assertNotNull($default);
        $this->assertSame('6', $default->getId());
    }

    public function testRemoveDeletesById(): void
    {
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->expects($this->once())->method('delete')->with(5);

        (new EnviatodoPackageCatalog($packageApi, new NullLogger()))->remove(5);

        $this->addToAssertionCount(1);
    }
}
