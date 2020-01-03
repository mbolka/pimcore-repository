<?php
/**
 * @category    pimcore-repository
 * @date        14/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Persisters;

use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadataInterface;
use Bolka\RepositoryBundle\ORM\Mapping\ElementMetadata;
use Bolka\RepositoryBundle\ORM\Mapping\ElementMetadataInterface;
use Bolka\RepositoryBundle\ORM\Persisters\Document\BasicPimcoreElementPersister;
use Bolka\RepositoryBundle\ORM\Persisters\Entity\BasicPimcoreEntityPersister;
use Bolka\RepositoryBundle\ORM\PimcoreElementManagerInterface;
use Pimcore\Model\FactoryInterface;

/**
 * Class EntityPersisterFactory
 * @package Bolka\RepositoryBundle\ORM\Entity
 */
class ElementPersisterFactory
{
    /**
     * @param PimcoreElementManagerInterface $em
     * @param ElementMetadataInterface       $classMetadata
     * @param FactoryInterface               $factory
     * @return PimcoreElementPersisterInterface
     */
    public function getEntityPersiter(
        PimcoreElementManagerInterface $em,
        ElementMetadataInterface $classMetadata,
        FactoryInterface $factory
    ) {
        if ($classMetadata instanceof ElementMetadata) {
            return new BasicPimcoreElementPersister($em, $classMetadata, $factory);
        } elseif ($classMetadata instanceof ClassMetadataInterface) {
            return new BasicPimcoreEntityPersister($em, $classMetadata, $factory);
        }
        throw new \RuntimeException(sprintf("Metadata class: %s is not supported", get_class($classMetadata)));
    }
}
