<?php
/**
 * @category    pimcore-repository
 * @date        12/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */
namespace Bolka\RepositoryBundle\ORM\Repository;

use Pimcore\Model\DataObject\Concrete;
use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadata;
use Bolka\RepositoryBundle\ORM\PimcoreEntityManagerInterface;
use Bolka\RepositoryBundle\ORM\PimcoreEntityRepository;
use Bolka\RepositoryBundle\ORM\PimcoreEntityRepositoryInterface;

/**
 * Class DefaultRepositoryFactory
 * @package Bolka\RepositoryBundle\Repository
 */
class DefaultRepositoryFactory implements RepositoryFactoryInterface
{
    /**
     * The list of EntityRepository instances.
     *
     * @var PimcoreEntityRepository[]
     */
    private $repositoryList = [];

    /**
     * @param PimcoreEntityManagerInterface $entityManager
     * @param                               $pimcoreClass
     * @return PimcoreEntityRepository|mixed
     * @throws \ReflectionException
     */
    public function getRepository(PimcoreEntityManagerInterface $entityManager, $pimcoreClass)
    {
        $reflection = new \ReflectionClass($pimcoreClass);
        if (!$reflection->isSubclassOf(Concrete::class)) {
            throw new \InvalidArgumentException('Pimcore Entity Manager supports only Pimcore Objects');
        }
        $repositoryHash = $entityManager->getClassMetadata($pimcoreClass)->getName() . spl_object_hash($entityManager);
        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }
        $this->repositoryList[$repositoryHash] = $this->createRepository($entityManager, $pimcoreClass);
        return $this->repositoryList[$repositoryHash];
    }

    /**
     * Create a new repository instance for an entity class.
     *
     * @param PimcoreEntityManagerInterface $entityManager The EntityManager instance.
     * @param string                        $pimcoreClass The name of the pimcore class.
     *
     * @return PimcoreEntityRepository
     */
    private function createRepository(PimcoreEntityManagerInterface $entityManager, $pimcoreClass)
    {
        /* @var $metadata ClassMetadata */
        $metadata            = $entityManager->getClassMetadata($pimcoreClass);
        $repositoryClassName = $metadata->customRepositoryClassName
            ?: $entityManager->getDefaultRepositoryClassName();
        try {
            $refClass = new \ReflectionClass($repositoryClassName);
            if (!$refClass->implementsInterface(PimcoreEntityRepositoryInterface::class)) {
                throw new \InvalidArgumentException('Repository must implements PimcoreEntityRepositoryInterface');
            }
        } catch (\ReflectionException $exception) {
            throw new \InvalidArgumentException('Repository does not exists');
        }
        return new $repositoryClassName($entityManager, $metadata);
    }
}
