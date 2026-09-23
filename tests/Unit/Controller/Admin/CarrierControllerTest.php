<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\CatalogApi;
use SarissaOps\Enviatodo\Api\ParcelApi;
use SarissaOps\Enviatodo\Model\Catalog\Catalog;
use SarissaOps\Enviatodo\Model\Parcel\ParcelService;
use SarissaOps\SyliusEnviatodoPlugin\Controller\Admin\CarrierController;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoCarrierCatalog;
use Symfony\Component\HttpFoundation\Request;

final class CarrierControllerTest extends TestCase
{
    public function testListActionExposesServicesAndCatalogs(): void
    {
        $parcelApi = $this->createMock(ParcelApi::class);
        $parcelApi->method('services')->willReturn([
            ParcelService::create([
                'parcel' => 'ESTAFETA',
                'provider_id' => '9',
                'services' => [['provider_service_id' => '11', 'label' => 'Aéreo', 'provider_id' => '9', 'max_weight' => 50]],
            ]),
        ]);
        $catalogApi = $this->createMock(CatalogApi::class);
        $catalogApi->method('productTypes')->willReturn(Catalog::create([
            'component' => 'c', 'type' => 'pts', 'items' => [['key' => '01010101', 'value' => 'Generic goods']],
        ]));
        $catalogApi->method('packageTypes')->willReturn(Catalog::create([
            'component' => 'c', 'type' => 'pkt', 'items' => [['key' => '1', 'value' => 'Box']],
        ]));

        $response = (new CarrierController(new EnviatodoCarrierCatalog($parcelApi, $catalogApi)))
            ->listAction(Request::create('/admin/enviatodo/carriers', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        /** @var list<array<string, mixed>> $services */
        $services = $data['services'];
        $this->assertCount(1, $services);
        $this->assertSame('9', $services[0]['provider_id']);
        /** @var array<string, string> $productTypes */
        $productTypes = $data['product_types'];
        $this->assertSame(['01010101' => 'Generic goods'], $productTypes);
    }
}
