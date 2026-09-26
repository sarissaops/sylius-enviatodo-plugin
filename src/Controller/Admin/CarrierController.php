<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoCarrierCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class CarrierController
{
    public function __construct(
        private readonly EnviatodoCarrierCatalog $carrierCatalog,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function listAction(Request $request): JsonResponse
    {
        $denied = AdminAccess::denyUnlessAdmin($this->authorizationChecker);
        if (null !== $denied) {
            return $denied;
        }

        try {
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

            $response = [
                'services' => $services,
                'product_types' => $this->carrierCatalog->productTypes(),
                'package_types' => $this->carrierCatalog->packageTypes(),
            ];
        } catch (EnviatodoException | HttpClientException | HttpServerException | \RuntimeException) {
            return new JsonResponse(['error' => 'catalog_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse($response);
    }
}
