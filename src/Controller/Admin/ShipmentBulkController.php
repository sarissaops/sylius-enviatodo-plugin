<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoLabelService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ShipmentBulkController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'enviatodo_bulk_labels';

    public function __construct(
        private readonly EnviatodoLabelService $labelService,
        private readonly EnviatodoShipmentRepositoryInterface $repository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function bulkLabelsAction(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token', '');
        if ('' === $token) {
            $fallback = $request->request->get('_csrf_token', '');
            $token = \is_string($fallback) ? $fallback : '';
        }
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            return new JsonResponse(['error' => 'invalid_csrf_token'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $shipmentIds = $body['shipment_ids'] ?? null;
        if (!\is_array($shipmentIds)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $guideIds = [];
        foreach ($shipmentIds as $shipmentId) {
            if (!\is_int($shipmentId)) {
                return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }
            $guideId = $this->repository->findById($shipmentId)?->getGuideId();
            if (null !== $guideId && is_numeric($guideId)) {
                $guideIds[] = (int) $guideId;
            }
        }
        if ([] === $guideIds) {
            return new JsonResponse(['files' => [], 'invalid' => []]);
        }

        $batch = $this->labelService->downloadBinaries($guideIds);

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
