<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use SarissaOps\SyliusEnviatodoPlugin\Controller\CsrfProtection;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPickupScheduler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PickupController
{
    public const CSRF_TOKEN_ID = 'enviatodo_pickup';

    public function __construct(
        private readonly EnviatodoPickupScheduler $scheduler,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Security $security,
    ) {
    }

    public function queueAction(Request $request): JsonResponse
    {
        $denied = AdminAccess::denyUnlessAdmin($this->authorizationChecker);
        if (null !== $denied) {
            return $denied;
        }

        $providersParam = $request->query->all()['providers'] ?? '';
        if (!\is_string($providersParam) || '' === trim($providersParam)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $providerIds = array_values(array_filter(array_map('trim', explode(',', $providersParam)), static fn (string $id): bool => '' !== $id));
        if ([] === $providerIds) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $orders = $this->scheduler->eligibleOrders($providerIds);
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
        $denied = AdminAccess::denyUnlessAdmin($this->authorizationChecker);
        if (null !== $denied) {
            return $denied;
        }

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
        $denied = AdminAccess::denyUnlessAdmin($this->authorizationChecker);
        if (null !== $denied) {
            return $denied;
        }

        $guard = $this->checkCsrf($request);
        if (null !== $guard) {
            return $guard;
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $description = $body['description'] ?? null;
        if (!\is_string($description) || '' === trim($description)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        // The acting admin comes from the security token, never the body.
        $userId = $this->security->getUser()?->getUserIdentifier() ?? '';

        try {
            $cancelled = $this->scheduler->cancelPickup($id, $description, $userId);
        } catch (\RuntimeException) {
            return new JsonResponse(['error' => 'pickup_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['cancelled' => $cancelled]);
    }

    private function checkCsrf(Request $request): ?JsonResponse
    {
        $guard = CsrfProtection::validate($request, $this->csrfTokenManager, self::CSRF_TOKEN_ID);
        if (null !== $guard) {
            return $guard;
        }

        return null;
    }
}
