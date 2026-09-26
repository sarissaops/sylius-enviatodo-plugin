<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Functional;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
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
        $container->setDefinition('doctrine', new Definition(ManagerRegistry::class));
        $container->setDefinition('logger', new Definition(LoggerInterface::class));
        $container->setDefinition('sylius.repository.order', new Definition(OrderRepositoryInterface::class));
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
            'sarissaops_enviatodo.order_api',
            'sarissaops_enviatodo.balance_api',
            'sarissaops_enviatodo.repository.shipment',
            'sarissaops_enviatodo.order_manager',
            'sarissaops_enviatodo.listener.order_paid',
            'sarissaops_enviatodo.guide_api',
            'sarissaops_enviatodo.label_service',
            'sarissaops_enviatodo.shipment_canceller',
            'sarissaops_enviatodo.controller.admin.shipment_bulk',
            'sarissaops_enviatodo.pickup_api',
            'sarissaops_enviatodo.pickup_scheduler',
            'sarissaops_enviatodo.controller.admin.pickup',
            'sarissaops_enviatodo.address_api',
            'sarissaops_enviatodo.package_api',
            'sarissaops_enviatodo.parcel_api',
            'sarissaops_enviatodo.catalog_api',
            'sarissaops_enviatodo.address_book',
            'sarissaops_enviatodo.package_catalog',
            'sarissaops_enviatodo.carrier_catalog',
            'sarissaops_enviatodo.checkout_defaults',
            'sarissaops_enviatodo.controller.admin.address',
            'sarissaops_enviatodo.controller.admin.package',
            'sarissaops_enviatodo.controller.admin.carrier',
            'sarissaops_enviatodo.state_processor.shop.live_rates',
            'sarissaops_enviatodo.state_processor.admin.bulk_labels',
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

    public function testOrderPaidListenerSubscribesToThePaidTransition(): void
    {
        $container = $this->build();

        $tags = $container->getDefinition('sarissaops_enviatodo.listener.order_paid')->getTag('kernel.event_listener');

        $this->assertCount(1, $tags);
        $this->assertSame('workflow.sylius_order_payment.completed.pay', $tags[0]['event']);
    }

    public function testControllersArePlainCallables(): void
    {
        $container = $this->build();

        foreach ([
            'sarissaops_enviatodo.controller.shop.quote',
            'sarissaops_enviatodo.controller.admin.shipment_bulk',
            'sarissaops_enviatodo.controller.admin.pickup',
        ] as $id) {
            $definition = $container->getDefinition($id);
            $this->assertCount(1, $definition->getTag('controller.service_arguments'), $id);
            $this->assertFalse($definition->hasTag('container.service_subscriber'), $id);
        }
    }

    public function testStateProcessorsAreTagged(): void
    {
        $container = $this->build();

        foreach ([
            'sarissaops_enviatodo.state_processor.shop.live_rates',
            'sarissaops_enviatodo.state_processor.admin.bulk_labels',
        ] as $id) {
            $this->assertCount(1, $container->getDefinition($id)->getTag('api_platform.state_processor'), $id);
        }
    }

    public function testExtensionExposesParametersAndValidationGroup(): void
    {
        $container = $this->build();

        foreach (['provider_map', 'insurance', 'pickup_auto_schedule', 'ui_enabled'] as $key) {
            $this->assertTrue($container->hasParameter('sarissa_ops_sylius_enviatodo.' . $key), $key);
        }
        $this->assertSame(
            ['sylius', 'enviatodo_select_shipping'],
            $container->getParameter('sylius.form.type.checkout_select_shipping.validation_groups'),
        );
    }
}
