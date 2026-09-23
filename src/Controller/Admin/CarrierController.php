<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoCarrierCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CarrierController
{
    public function __construct(private readonly EnviatodoCarrierCatalog $carrierCatalog)
    {
    }

    public function listAction(Request $request): JsonResponse
    {
        $services = [];
        foreach ($this->carrierCatalog->services() as $group) {
            $rows = [];
            foreach ($group->getServices() as $service) {
                $rows[] = [
                    'provider_service_id' => $service->getProviderServiceId(),
                    'label' => $service->getLabel(),
                    'via_transport' => $service->getViaTransport(),
                    'max_height' => $service->getMaxHeight(),
                    'max_width' => $service->getMaxWidth(),
                    'max_length' => $service->getMaxLength(),
                    'max_weight' => $service->getMaxWeight(),
                ];
            }
            $services[] = [
                'provider_id' => $group->getProviderId(),
                'parcel' => $group->getParcel(),
                'services' => $rows,
            ];
        }

        return new JsonResponse([
            'services' => $services,
            'product_types' => $this->carrierCatalog->productTypes(),
            'package_types' => $this->carrierCatalog->packageTypes(),
        ]);
    }
}
