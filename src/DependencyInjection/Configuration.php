<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sarissa_ops_sylius_enviatodo');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->integerNode('origin_address_id')
                    ->defaultNull()
                    ->info('Default Enviatodo origin address id used for checkout quotes.')
                ->end()
                ->integerNode('default_package_id')
                    ->defaultNull()
                    ->info('Default Enviatodo package id used for checkout quotes.')
                ->end()
                ->booleanNode('insurance')->defaultFalse()->end()
                ->booleanNode('pickup_auto_schedule')->defaultFalse()->end()
                ->arrayNode('provider_map')
                    ->useAttributeAsKey('method_code')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('provider_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('provider_service_id')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                    ->info('Sylius shipping-method code -> Enviatodo provider/service pair.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
