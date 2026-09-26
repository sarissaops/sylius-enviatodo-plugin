<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\SyliusEnviatodoPlugin\Controller\CsrfProtection;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoLabelService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ShipmentBulkController
{
    public const CSRF_TOKEN_ID = 'enviatodo_bulk_labels';

    private const MAX_SHIPMENTS = 100;

    public function __construct(
        private readonly EnviatodoLabelService $labelService,
        private readonly EnviatodoShipmentRepositoryInterface $repository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly LoggerInterface $logger,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function bulkLabelsAction(Request $request): JsonResponse
    {
        $denied = AdminAccess::denyUnlessAdmin($this->authorizationChecker);
        if (null !== $denied) {
            return $denied;
        }

        $guard = CsrfProtection::validate($request, $this->csrfTokenManager, self::CSRF_TOKEN_ID);
        if (null !== $guard) {
            return $guard;
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $shipmentIds = $body['shipment_ids'] ?? null;
        if (!\is_array($shipmentIds)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $requestedIds = [];
        foreach ($shipmentIds as $shipmentId) {
            if (!\is_int($shipmentId)) {
                return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }
            $requestedIds[] = $shipmentId;
        }
        $requestedIds = array_values(array_unique($requestedIds));
        if (self::MAX_SHIPMENTS < \count($requestedIds)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $guideIds = [];
        foreach ($requestedIds as $shipmentId) {
            $guideId = $this->repository->findOneByShipmentId($shipmentId)?->getGuideId();
            if (null !== $guideId && is_numeric($guideId)) {
                $guideIds[] = (int) $guideId;
            }
        }
        // UI-tolerant contract: total misses return empty 200 so the grid
        // action never breaks; the headless twin answers 422 instead.
        if ([] === $guideIds) {
            return new JsonResponse(['files' => [], 'invalid' => []]);
        }

        try {
            $batch = $this->labelService->downloadBinaries($guideIds);
        } catch (EnviatodoException | HttpClientException | HttpServerException | \RuntimeException $exception) {
            $this->logger->error('Enviatodo bulk label download failed.', ['error' => $exception->getMessage()]);

            return new JsonResponse(['error' => 'labels_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        $files = [];
        foreach ($batch->getFiles() as $file) {
            $files[] = [
                'guide_id' => $file->getGuideId(),
                'name' => $file->getName(),
                'binary' => $file->getBinary(),
                'mime' => $file->getMime(),
            ];
        }
        $invalid = [];
        foreach ($batch->getInvalidFiles() as $invalidFile) {
            $invalid[] = ['guide_id' => $invalidFile->getGuideId(), 'reason' => $invalidFile->getReason()];
        }

        return new JsonResponse(['files' => $files, 'invalid' => $invalid]);
    }
}
