<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use Doctrine\Persistence\ManagerRegistry;
use SarissaOps\SyliusEnviatodoPlugin\Controller\CsrfProtection;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\ShipmentCanceller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ShipmentCancelController
{
    public const CSRF_TOKEN_ID = 'enviatodo_shipment_cancel';

    public function __construct(
        private readonly ShipmentCanceller $canceller,
        private readonly EnviatodoShipmentRepositoryInterface $repository,
        private readonly ManagerRegistry $registry,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function cancelAction(Request $request, int $id): JsonResponse
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
        $reasonValue = $body['reason_value'] ?? null;
        $reasonText = $body['reason_text'] ?? null;
        if (!\is_string($reasonValue) || '' === trim($reasonValue) || !\is_string($reasonText) || '' === trim($reasonText)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $record = $this->repository->findOneByShipmentId($id);
        $trackingId = $record?->getTrackingId();
        if (null === $trackingId || '' === $trackingId) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $window = $this->canceller->cancel([$trackingId], $reasonValue, $reasonText);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            return new JsonResponse(['error' => 'cancel_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        $record->markCancelled();
        $this->registry->getManager()->flush();

        return new JsonResponse([
            'cancelled' => true,
            'tracking_id' => $trackingId,
            'cancelled_at' => $window['cancelled_at'],
            'refunded_at' => $window['refunded_at'],
        ]);
    }
}
