<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin;

use SarissaOps\SyliusEnviatodoPlugin\DependencyInjection\Compiler\EnviatodoValidationPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SarissaOpsSyliusEnviatodoPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new EnviatodoValidationPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
