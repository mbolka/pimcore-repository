<?php
/**
 * @category    tigerspike
 * @date        12/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace RepositoryBundle\Repository;

use Doctrine\Common\Persistence\ObjectRepository;
use RepositoryBundle\Common\PimcoreEntityManagerInterface;
use RepositoryBundle\Common\Repository\RepositoryFactoryInterface;
use RepositoryBundle\ORM\Mapping\ClassMetadata;

/**
 * Class DefaultRepositoryFactory
 * @package RepositoryBundle\Repository
 */
class DefaultRepositoryFactory implements RepositoryFactoryInterface
{
    /**
     * The list of EntityRepository instances.
     *
     * @var ObjectRepository[]
     */
    private $repositoryList = [];

    /**
     * {@inheritdoc}
     */
    public function getRepository(PimcoreEntityManagerInterface $entityManager, $entityName)
    {
        $repositoryHash = $entityManager->getClassMetadata($entityName)->getName() . spl_object_hash($entityManager);

        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }
        $this->repositoryList[$repositoryHash] = $this->createRepository($entityManager, $entityName);

        return $this->repositoryList[$repositoryHash];
    }

    /**
     * Create a new repository instance for an entity class.
     *
     * @param PimcoreEntityManagerInterface $entityManager The EntityManager instance.
     * @param string                        $entityName The name of the entity.
     *
     * @return ObjectRepository
     */
    private function createRepository(PimcoreEntityManagerInterface $entityManager, $entityName)
    {
        /* @var $metadata ClassMetadata */
        $metadata            = $entityManager->getClassMetadata($entityName);
        $repositoryClassName = $metadata->customRepositoryClassName
            ?: $entityManager->getDefaultRepositoryClassName();

        return new $repositoryClassName($entityManager, $metadata);
    }
}
