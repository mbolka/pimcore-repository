<?php
/**
 * @category    pimcore-repository
 * @date        11/06/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */
namespace Bolka\RepositoryBundle\ORM\Compiler;

use Bolka\RepositoryBundle\ORM\Mapping\PimcoreElementMetadataFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ClassMetadataFactoryCompiler
 * @package Bolka\RepositoryBundle\ORM\Compiler
 */
class ClassMetadataFactoryCompiler implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $contextDefinition  = $container->findDefinition(
            PimcoreElementMetadataFactory::class
        );
        $strategyServiceIds = array_keys(
            $container->findTaggedServiceIds('pimcore.repository')
        );
        foreach ($strategyServiceIds as $strategyServiceId) {
            $definition = $container->getDefinition($strategyServiceId);
            $tag = $definition->getTag('pimcore.repository');
            if (!$tag || !array_key_exists('class', $tag[0])) {
                continue;
            }
            $contextDefinition->addMethodCall(
                'addCustomRepository',
                [$strategyServiceId, $tag[0]['class']]
            );
        }
    }
}
