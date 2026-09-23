<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\Enviatodo\Api\CatalogApi;
use SarissaOps\Enviatodo\Api\ParcelApi;
use SarissaOps\Enviatodo\Model\Catalog\Catalog;
use SarissaOps\Enviatodo\Model\Parcel\ParcelService;

/**
 * Carrier and SAT catalog reads. Services carry max dimensions/weight for
 * rule checking; catalogs feed product/package type dropdowns.
 */
final class EnviatodoCarrierCatalog
{
    public function __construct(
        private readonly ParcelApi $parcelApi,
        private readonly CatalogApi $catalogApi,
    ) {
    }

    /** @return list<ParcelService> */
    public function services(): array
    {
        $result = $this->parcelApi->services();
        if (!\is_array($result)) {
            throw new \RuntimeException('ParcelApi must hydrate ParcelService models for listing.');
        }

        return $result;
    }

    /** @return array<string, string> */
    public function productTypes(): array
    {
        return $this->catalogItems($this->catalogApi->productTypes());
    }

    /** @return array<string, string> */
    public function packageTypes(): array
    {
        return $this->catalogItems($this->catalogApi->packageTypes());
    }

    /**
     * @return array<string, string>
     */
    private function catalogItems(mixed $catalog): array
    {
        if (!$catalog instanceof Catalog) {
            throw new \RuntimeException('CatalogApi must hydrate Catalog models for type listing.');
        }

        $items = [];
        foreach ($catalog->getItems() as $row) {
            $items[$row['key']] = $row['value'];
        }

        return $items;
    }
}
