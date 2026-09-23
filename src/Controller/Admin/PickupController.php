<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPickupScheduler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PickupController
{
    public const CSRF_TOKEN_ID = 'enviatodo_pickup';

    public function __construct(
        private readonly EnviatodoPickupScheduler $scheduler,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function queueAction(Request $request): JsonResponse
    {
        $providers = $request->query->get('providers', '');
        if ('' === $providers) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $orders = $this->scheduler->eligibleOrders(explode(',', $providers));
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            return new JsonResponse(['error' => 'pickup_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                'id' => $order->getId(),
                'tracking_id' => $order->getTrackingId(),
                'provider_name' => $order->getProviderName(),
                'provider_id' => $order->getProviderId(),
                'service_name' => $order->getServiceName(),
                'origin' => $order->getOrigin(),
                'created_at' => $order->getCreatedAt(),
            ];
        }

        return new JsonResponse(['orders' => $rows]);
    }

    public function createAction(Request $request): JsonResponse
    {
        $guard = $this->checkCsrf($request);
        if (null !== $guard) {
            return $guard;
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $pickupData = [];
        foreach ($body as $key => $value) {
            if (\is_string($key)) {
                $pickupData[$key] = $value;
            }
        }

        try {
            $created = $this->scheduler->schedule($pickupData);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException) {
            return new JsonResponse(['error' => 'pickup_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['created' => $created]);
    }

    public function cancelAction(Request $request, int $id): JsonResponse
    {
        $guard = $this->checkCsrf($request);
        if (null !== $guard) {
            return $guard;
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $description = $body['description'] ?? null;
        $userId = $body['user_id'] ?? null;
        if (!\is_string($description) || '' === $description || (!\is_string($userId) && !\is_int($userId))) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $cancelled = $this->scheduler->cancelPickup($id, $description, (string) $userId);
        } catch (\RuntimeException) {
            return new JsonResponse(['error' => 'pickup_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['cancelled' => $cancelled]);
    }

    private function checkCsrf(Request $request): ?JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token', '');
        if ('' === $token) {
            $fallback = $request->request->get('_csrf_token', '');
            $token = \is_string($fallback) ? $fallback : '';
        }
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            return new JsonResponse(['error' => 'invalid_csrf_token'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
