<?php
/**
 * @category    pimcore-repository
 * @date        14/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Persisters\Entity;

use Pimcore\Model\FactoryInterface;
use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadataInterface;
use Bolka\RepositoryBundle\ORM\PimcoreEntityManagerInterface;

/**
 * Class EntityPersisterFactory
 * @package Bolka\RepositoryBundle\ORM\Persisters\Entity
 */
class EntityPersisterFactory
{
    /**
     * @param PimcoreEntityManagerInterface $em
     * @param ClassMetadataInterface        $classMetadata
     * @param FactoryInterface              $factory
     * @return BasicPimcoreEntityPersister
     */
    public function getEntityPersiter(
        PimcoreEntityManagerInterface $em,
        ClassMetadataInterface $classMetadata,
        FactoryInterface $factory
    ) {
        return new BasicPimcoreEntityPersister($em, $classMetadata, $factory);
    }
}
