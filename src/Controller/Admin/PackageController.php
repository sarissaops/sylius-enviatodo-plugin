<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use SarissaOps\Enviatodo\Model\Package\Package;
use SarissaOps\SyliusEnviatodoPlugin\Controller\CsrfProtection;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPackageCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PackageController
{
    public const CSRF_TOKEN_ID = 'enviatodo_package';

    public function __construct(
        private readonly EnviatodoPackageCatalog $packageCatalog,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function listAction(): JsonResponse
    {
        $packages = [];
        foreach ($this->packageCatalog->all() as $package) {
            $packages[] = $this->map($package);
        }

        return new JsonResponse(['packages' => $packages]);
    }

    public function showAction(Request $request, int $id): JsonResponse
    {
        $package = $this->packageCatalog->get($id);
        if (null === $package) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['package' => $this->map($package)]);
    }

    public function saveAction(Request $request): JsonResponse
    {
        $guard = CsrfProtection::validate($request, $this->csrfTokenManager, self::CSRF_TOKEN_ID);
        if (null !== $guard) {
            return $guard;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $payload = [];
        foreach ($data as $key => $value) {
            if (\is_string($key)) {
                $payload[$key] = $value;
            }
        }

        try {
            $package = $this->packageCatalog->save($payload);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            return new JsonResponse(['error' => 'package_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['package' => $this->map($package)]);
    }

    public function deleteAction(Request $request, int $id): JsonResponse
    {
        $guard = CsrfProtection::validate($request, $this->csrfTokenManager, self::CSRF_TOKEN_ID);
        if (null !== $guard) {
            return $guard;
        }

        $this->packageCatalog->remove($id);

        return new JsonResponse(['deleted' => true]);
    }

    /** @return array<string, mixed> */
    private function map(Package $package): array
    {
        return [
            'id' => $package->getId(),
            'name' => $package->getName(),
            'product_type' => $package->getProductType(),
            'unit_type' => $package->getUnitType(),
            'package_content' => $package->getPackageContent(),
            'amount_pkg' => $package->getAmountPkg(),
            'height' => $package->getHeight(),
            'width' => $package->getWidth(),
            'length' => $package->getLength(),
            'weight' => $package->getWeight(),
            'real_weight' => $package->getRealWeight(),
            'volumetric_weight' => $package->getVolumetricWeight(),
            'bill_weight' => $package->getBillWeight(),
            'default_pkg' => $package->getDefaultPkg(),
        ];
    }
}
