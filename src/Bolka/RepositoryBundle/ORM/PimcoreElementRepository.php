<?php
/**
 * @category    bosch-stuttgart
 * @date        04/11/2019
 * @author      Michał Bolka <mbolka@divante.co>
 * @copyright   Copyright (c) 2019 Divante Ltd. (https://divante.co)
 */

namespace Bolka\RepositoryBundle\ORM;

use Bolka\RepositoryBundle\Common\Collections\Criteria;
use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadataInterface;
use Bolka\RepositoryBundle\ORM\Mapping\ElementMetadataInterface;
use Doctrine\ORM\ORMException;

/**
 * Class PimcoreElementRepository
 * @package Bolka\RepositoryBundle\ORM
 */
class PimcoreElementRepository implements PimcoreElementRepositoryInterface
{

    /** @var PimcoreElementManager */
    protected $em;

    /** @var ElementMetadataInterface */
    protected $class;
    /** @var string */
    private $entityName;
    /** @var bool */
    private $omitCatalogs = true;
    /**
     * Initializes a new <tt>EntityRepository</tt>.
     *
     * @param PimcoreElementManager    $em The EntityManager to use.
     * @param ElementMetadataInterface $class The class descriptor.
     */
    public function __construct(PimcoreElementManager $em, ElementMetadataInterface $class)
    {
        $this->entityName = $class->name;
        $this->em         = $em;
        $this->class      = $class;
    }

    /**
     * Clears in-memory objects
     */
    public function clear()
    {
        $this->em->clear($this->class->getName());
    }

    /**
     * Finds an object by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     *
     * @return object|null The object.
     * @throws ORMException
     */
    public function find($id)
    {
        return $this->em->find($this->entityName, $id);
    }

    /**
     * Finds all objects in the repository.
     *
     * @return object[] The objects.
     */
    public function findAll()
    {
        return $this->findBy([]);
    }

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param mixed[]       $criteria
     * @param string[]|null $orderBy
     * @param int|null      $limit
     * @param int|null      $offset
     *
     * @return object[] The objects.
     *
     * @throws UnexpectedValueException
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $persister = $this->em->getUnitOfWork()->getEntityPersister($this->entityName);
        if ($this->omitCatalogs) {
            $criteria[] = ['condition' => 'type <> ?', 'variable' => 'folder'];
        }
        $objects = $persister->loadAll($criteria, $orderBy, $limit, $offset);
        foreach ($objects as $object) {
            $this->getEntityManager()
                ->registerManaged($object, $this->getClassMetadata()->getIdentifierFieldNames());
        }
        return $objects;
    }

    /**
     * Finds a single object by a set of criteria.
     *
     * @param mixed[]    $criteria The criteria.
     *
     * @param array|null $orderBy
     * @return object|null The object.
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        $persister = $this->em
            ->getUnitOfWork()
            ->getEntityPersister($this->entityName);
        if ($this->omitCatalogs) {
            $criteria[] = ['condition' => 'type <> ?', 'variable' => 'folder'];
        }
        $object = $persister->load($criteria, 1, $orderBy);
        if ($object !== null) {
            $this->getEntityManager()
                ->registerManaged($object, $this->getClassMetadata()->getIdentifierFieldNames());
        }
        return $object;
    }

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->getEntityName();
    }

    /**
     * Selects all elements from a selectable that match the expression and
     * returns a new collection containing these elements.
     *
     * @param \Bolka\RepositoryBundle\Common\Collections\Criteria $criteria
     *
     * @psalm-return Collection<TKey,T>
     * @return LazyCriteriaCollection
     */
    public function matching(\Doctrine\Common\Collections\Criteria $criteria)
    {
        $persister = $this->em->getUnitOfWork()->getEntityPersister($this->entityName);

        return new LazyCriteriaCollection($persister, $criteria);
    }

    /**
     * @param array $criteria
     * @return int
     */
    public function count(array $criteria)
    {
        return $this->em->getUnitOfWork()->getEntityPersister($this->entityName)->count($criteria);
    }

    /**
     * @return string
     */
    protected function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @return PimcoreElementManager
     */
    protected function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @return ClassMetadataInterface
     */
    protected function getClassMetadata()
    {
        return $this->class;
    }

    /**
     * @param bool $omit
     */
    public function setOmitCatalogs(bool $omit)
    {
        $this->omitCatalogs = $omit;
    }
}
