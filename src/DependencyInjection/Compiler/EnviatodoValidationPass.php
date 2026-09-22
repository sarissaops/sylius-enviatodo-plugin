<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Scopes the plugin constraint to the select-shipping step. A dedicated group
 * keeps validation off cart/address/payment steps; sylius_checkout_complete
 * stays as the fallback gate.
 */
final class EnviatodoValidationPass implements CompilerPassInterface
{
    public const VALIDATION_GROUP = 'enviatodo_select_shipping';

    private const PARAMETER = 'sylius.form.type.checkout_select_shipping.validation_groups';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }
        $groups = $container->getParameter(self::PARAMETER);
        if (!\is_array($groups)) {
            return;
        }
        if (\in_array(self::VALIDATION_GROUP, $groups, true)) {
            return;
        }
        $groups[] = self::VALIDATION_GROUP;
        $container->setParameter(self::PARAMETER, $groups);
    }
}
