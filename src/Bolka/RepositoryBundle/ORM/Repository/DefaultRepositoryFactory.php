<?php
/**
 * @category    pimcore-repository
 * @date        12/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Repository;

use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadata;
use Bolka\RepositoryBundle\ORM\PimcoreElementManagerInterface;
use Bolka\RepositoryBundle\ORM\PimcoreElementRepository;
use Bolka\RepositoryBundle\ORM\PimcoreElementRepositoryInterface;
use Bolka\RepositoryBundle\ORM\PimcoreEntityRepository;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\AbstractElement;

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
     * @param PimcoreElementManagerInterface $entityManager
     * @param                                $pimcoreClass
     * @return PimcoreEntityRepository|mixed
     * @throws \ReflectionException
     */
    public function getRepository(PimcoreElementManagerInterface $entityManager, $pimcoreClass)
    {
        $reflection = new \ReflectionClass($pimcoreClass);
        if ($reflection->isSubclassOf(Concrete::class)) {
            $repositoryClassName = PimcoreEntityRepository::class;
        } elseif ($reflection->isSubclassOf(AbstractElement::class)) {
            $repositoryClassName = PimcoreElementRepository::class;
        } else {
            throw new \RuntimeException(
                sprintf(
                    "Pimcore Entity Manager supports only Pimcore Objects, class: %s given",
                    $pimcoreClass
                )
            );
        }
        $repositoryHash = $entityManager->getClassMetadata($pimcoreClass)->getName() . spl_object_hash($entityManager);
        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }
        $this->repositoryList[$repositoryHash] = $this->createRepository(
            $entityManager,
            $repositoryClassName,
            $pimcoreClass
        );
        return $this->repositoryList[$repositoryHash];
    }

    /**
     * Create a new repository instance for an entity class.
     *
     * @param PimcoreElementManagerInterface $entityManager The EntityManager instance.
     * @param string                         $repositoryClassName
     * @param string                         $pimcoreClass The name of the pimcore class.
     *
     * @return PimcoreEntityRepository
     * @throws \ReflectionException
     */
    private function createRepository(
        PimcoreElementManagerInterface $entityManager,
        string $repositoryClassName,
        $pimcoreClass
    ) {
        /* @var $metadata ClassMetadata */
        $metadata = $entityManager->getClassMetadata($pimcoreClass);
        if ($metadata->customRepositoryClassName) {
            $repositoryClassName = $metadata->customRepositoryClassName;
        }
        try {
            $refClass = new \ReflectionClass($repositoryClassName);
            if (!$refClass->implementsInterface(PimcoreElementRepositoryInterface::class)) {
                throw new \InvalidArgumentException(
                    'Repository must implements PimcoreElementRepositoryInterface'
                );
            }
        } catch (\ReflectionException $exception) {
            throw new \InvalidArgumentException('Repository does not exists');
        }
        return new $repositoryClassName($entityManager, $metadata);
    }
}
