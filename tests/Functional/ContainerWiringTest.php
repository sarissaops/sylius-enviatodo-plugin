<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Functional;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Enviatodo;
use SarissaOps\SyliusEnviatodoPlugin\DependencyInjection\Compiler\EnviatodoValidationPass;
use SarissaOps\SyliusEnviatodoPlugin\DependencyInjection\SarissaOpsSyliusEnviatodoExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ContainerWiringTest extends TestCase
{
    private function build(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(Enviatodo::class, new Definition(Enviatodo::class));
        $container->setDefinition('security.csrf.token_manager', new Definition(CsrfTokenManagerInterface::class));
        $container->setParameter('sylius.form.type.checkout_select_shipping.validation_groups', ['sylius']);

        $extension = new SarissaOpsSyliusEnviatodoExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('sarissa_ops_sylius_enviatodo', []);
        $container->addCompilerPass(new EnviatodoValidationPass());
        // Keep private services: this test asserts wiring, not removal.
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();

        return $container;
    }

    public function testServicesAreWired(): void
    {
        $container = $this->build();

        foreach ([
            'sarissaops_enviatodo.provider_map',
            'sarissaops_enviatodo.quote_api',
            'sarissaops_enviatodo.zip_code_api',
            'sarissaops_enviatodo.component_calculator',
            'sarissaops_enviatodo.shipping_calculator',
            'sarissaops_enviatodo.validator.method_available',
            'sarissaops_enviatodo.controller.shop.quote',
        ] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
    }

    public function testShippingCalculatorCarriesTheSyliusTag(): void
    {
        $container = $this->build();

        $tags = $container->getDefinition('sarissaops_enviatodo.shipping_calculator')->getTag('sylius.shipping_calculator');

        $this->assertCount(1, $tags);
        $this->assertSame('enviatodo', $tags[0]['calculator']);
        $this->assertNotEmpty($tags[0]['label']);
    }

    public function testExtensionExposesParametersAndValidationGroup(): void
    {
        $container = $this->build();

        foreach (['token', 'endpoint', 'provider_map', 'insurance', 'pickup_auto_schedule'] as $key) {
            $this->assertTrue($container->hasParameter('sarissa_ops_sylius_enviatodo.' . $key), $key);
        }
        $this->assertSame(
            ['sylius', 'enviatodo_select_shipping'],
            $container->getParameter('sylius.form.type.checkout_select_shipping.validation_groups'),
        );
    }
}
