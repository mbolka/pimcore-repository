<?php

namespace Bolka\RepositoryBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Bolka\RepositoryBundle\ORM\Compiler\ClassMetadataFactoryCompiler;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class RepositoryBundle
 * @package Bolka\RepositoryBundle
 */
class RepositoryBundle extends AbstractPimcoreBundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ClassMetadataFactoryCompiler());
    }
}
