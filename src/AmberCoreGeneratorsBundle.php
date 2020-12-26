<?php


namespace AmberCore\Generator;

use AmberCore\Generator\DependencyInjection\AmberCoreGeneratorExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AmberCoreGeneratorsBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    public function getContainerExtension()
    {
        return new AmberCoreGeneratorExtension();
    }
}