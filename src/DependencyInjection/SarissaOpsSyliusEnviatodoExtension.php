<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SarissaOpsSyliusEnviatodoExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);

        foreach (['token', 'endpoint', 'api_key', 'app_name'] as $key) {
            $value = $config[$key] ?? null;
            if (!is_string($value)) {
                throw new \InvalidArgumentException(sprintf('The "%s" option must be a string.', $key));
            }
            $container->setParameter('sarissa_ops_sylius_enviatodo.' . $key, $value);
        }

        foreach (['origin_address_id', 'default_package_id'] as $key) {
            $value = $config[$key] ?? null;
            if (!is_int($value) && null !== $value) {
                throw new \InvalidArgumentException(sprintf('The "%s" option must be an integer or null.', $key));
            }
            $container->setParameter('sarissa_ops_sylius_enviatodo.' . $key, $value);
        }

        foreach (['insurance', 'pickup_auto_schedule'] as $key) {
            $value = $config[$key] ?? null;
            if (!is_bool($value)) {
                throw new \InvalidArgumentException(sprintf('The "%s" option must be a boolean.', $key));
            }
            $container->setParameter('sarissa_ops_sylius_enviatodo.' . $key, $value);
        }

        $allowlist = $config['provider_allowlist'] ?? null;
        if (!is_array($allowlist)) {
            throw new \InvalidArgumentException('The "provider_allowlist" option must be an array.');
        }
        $container->setParameter('sarissa_ops_sylius_enviatodo.provider_allowlist', $allowlist);

        $providerMap = $config['provider_map'] ?? null;
        if (!is_array($providerMap)) {
            throw new \InvalidArgumentException('The "provider_map" option must be an array.');
        }
        $container->setParameter('sarissa_ops_sylius_enviatodo.provider_map', $providerMap);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');

        // UI-only wiring stays out of headless applications. The flag is set
        // in prepend(), which runs on the real container: load() itself runs
        // on a temporary copy where hasExtension() is unreliable.
        if ($container->hasParameter('sarissa_ops_sylius_enviatodo.ui_enabled') &&
            true === $container->getParameter('sarissa_ops_sylius_enviatodo.ui_enabled')
        ) {
            $loader->load('twig.xml');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);

        $container->setParameter('sarissa_ops_sylius_enviatodo.ui_enabled', $container->hasExtension('twig'));
    }

    protected function getMigrationsNamespace(): string
    {
        return 'SarissaOps\SyliusEnviatodoPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@SarissaOpsSyliusEnviatodoPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
