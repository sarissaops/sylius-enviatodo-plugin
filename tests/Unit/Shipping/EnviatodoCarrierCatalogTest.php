<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\CatalogApi;
use SarissaOps\Enviatodo\Api\ParcelApi;
use SarissaOps\Enviatodo\Model\Catalog\Catalog;
use SarissaOps\Enviatodo\Model\Parcel\ParcelService;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoCarrierCatalog;

final class EnviatodoCarrierCatalogTest extends TestCase
{
    public function testServicesListsParcelGroups(): void
    {
        $parcelApi = $this->createMock(ParcelApi::class);
        $parcelApi->expects($this->once())->method('services')->willReturn([
            ParcelService::create([
                'parcel' => 'ESTAFETA',
                'provider_id' => '9',
                'services' => [['provider_service_id' => '11', 'label' => 'Aéreo', 'provider_id' => '9', 'max_weight' => 50]],
            ]),
        ]);

        $services = (new EnviatodoCarrierCatalog($parcelApi, $this->createMock(CatalogApi::class)))->services();

        $this->assertCount(1, $services);
        $this->assertSame('9', $services[0]->getProviderId());
    }

    public function testProductAndPackageTypesMapKeysToValues(): void
    {
        $catalogApi = $this->createMock(CatalogApi::class);
        $catalogApi->method('productTypes')->willReturn(Catalog::create([
            'component' => 'c', 'type' => 'pts', 'items' => [['key' => '01010101', 'value' => 'Generic goods']],
        ]));
        $catalogApi->method('packageTypes')->willReturn(Catalog::create([
            'component' => 'c', 'type' => 'pkt', 'items' => [['key' => '1', 'value' => 'Box']],
        ]));

        $catalog = new EnviatodoCarrierCatalog($this->createMock(ParcelApi::class), $catalogApi);

        $this->assertSame(['01010101' => 'Generic goods'], $catalog->productTypes());
        $this->assertSame(['1' => 'Box'], $catalog->packageTypes());
    }

    public function testNonModelHydrationThrows(): void
    {
        $catalogApi = $this->createMock(CatalogApi::class);
        $catalogApi->method('productTypes')->willReturn([]);

        $this->expectException(\RuntimeException::class);

        (new EnviatodoCarrierCatalog($this->createMock(ParcelApi::class), $catalogApi))->productTypes();
    }
}
