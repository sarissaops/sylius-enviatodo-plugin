<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Functional;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Enviatodo;
use SarissaOps\SyliusEnviatodoPlugin\DependencyInjection\Compiler\EnviatodoValidationPass;
use SarissaOps\SyliusEnviatodoPlugin\DependencyInjection\SarissaOpsSyliusEnviatodoExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Headless compatibility: UI wiring (Twig services) loads only when a Twig
 * extension is registered. Domain, API and Doctrine wiring always load.
 */
final class HeadlessCompatibilityTest extends TestCase
{
    private function build(bool $withTwig): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(Enviatodo::class, new Definition(Enviatodo::class));
        $container->setDefinition('security.csrf.token_manager', new Definition(CsrfTokenManagerInterface::class));

        if ($withTwig) {
            $container->registerExtension(new class() extends Extension {
                public function getAlias(): string
                {
                    return 'twig';
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }
            });
        }

        $extension = new SarissaOpsSyliusEnviatodoExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('sarissa_ops_sylius_enviatodo', []);
        $container->addCompilerPass(new EnviatodoValidationPass());
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();

        return $container;
    }

    public function testUiServicesAreAbsentWithoutTwig(): void
    {
        $container = $this->build(false);

        $this->assertFalse($container->hasDefinition('sarissaops_enviatodo.twig.admin'));
        $this->assertTrue($container->hasDefinition('sarissaops_enviatodo.shipping_calculator'));
        $this->assertTrue($container->hasDefinition('sarissaops_enviatodo.order_manager'));
    }

    public function testUiServicesLoadWithTwig(): void
    {
        $container = $this->build(true);

        $this->assertTrue($container->hasDefinition('sarissaops_enviatodo.twig.admin'));

        $tags = $container->getDefinition('sarissaops_enviatodo.twig.admin')->getTag('twig.extension');

        $this->assertCount(1, $tags);
    }
}
