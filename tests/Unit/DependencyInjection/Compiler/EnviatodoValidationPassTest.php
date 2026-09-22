<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\DependencyInjection\Compiler\EnviatodoValidationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EnviatodoValidationPassTest extends TestCase
{
    public function testAppendsThePluginValidationGroup(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('sylius.form.type.checkout_select_shipping.validation_groups', ['sylius']);

        (new EnviatodoValidationPass())->process($container);

        $this->assertSame(
            ['sylius', 'enviatodo_select_shipping'],
            $container->getParameter('sylius.form.type.checkout_select_shipping.validation_groups'),
        );
    }

    public function testDoesNotDuplicateTheGroup(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('sylius.form.type.checkout_select_shipping.validation_groups', ['sylius', 'enviatodo_select_shipping']);

        (new EnviatodoValidationPass())->process($container);

        $this->assertSame(
            ['sylius', 'enviatodo_select_shipping'],
            $container->getParameter('sylius.form.type.checkout_select_shipping.validation_groups'),
        );
    }

    public function testSkipsContainersWithoutCheckoutForm(): void
    {
        $container = new ContainerBuilder();

        (new EnviatodoValidationPass())->process($container);

        $this->assertFalse($container->hasParameter('sylius.form.type.checkout_select_shipping.validation_groups'));
    }
}
