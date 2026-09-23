<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Shared CSRF guard for the plugin JSON controllers. Returns the 403 response
 * on failure, null when the request may proceed.
 */
final class CsrfProtection
{
    public static function validate(Request $request, CsrfTokenManagerInterface $csrfTokenManager, string $tokenId): ?JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token', '');
        if ('' === $token) {
            $fallback = $request->request->get('_csrf_token', '');
            $token = \is_string($fallback) ? $fallback : '';
        }
        if (!$csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token))) {
            return new JsonResponse(['error' => 'invalid_csrf_token'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
