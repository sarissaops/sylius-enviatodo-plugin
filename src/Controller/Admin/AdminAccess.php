<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Single authorization gate for the plugin's MVC admin endpoints: the
 * routes live under the admin firewall, but a host app may mount them
 * anywhere, so every action verifies the administration role itself
 * instead of trusting the prefix.
 */
final class AdminAccess
{
    public static function denyUnlessAdmin(AuthorizationCheckerInterface $authorizationChecker): ?JsonResponse
    {
        if ($authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')) {
            return null;
        }

        return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
    }
}
