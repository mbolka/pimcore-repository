<?php
/**
 * @category    tigerspike
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace RepositoryBundle\ORM;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\ORMException;
use RepositoryBundle\Common\Persistence\Mapping\ClassMetadata;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use UnexpectedValueException;

/**
 * Class PimcoreEntityRepository
 * @package RepositoryBundle\ORM
 */
class PimcoreEntityRepository implements ObjectRepository, Selectable
{
    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ClassMetadata
     */
    protected $class;

    /**
     * Initializes a new <tt>EntityRepository</tt>.
     *
     * @param EntityManager $em The EntityManager to use.
     * @param ClassMetadata $class The class descriptor.
     */
    public function __construct(EntityManager $em, ClassMetadata $class)
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
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
    {
        $persister = $this->em->getUnitOfWork()->getEntityPersister($this->entityName);
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
     * @param Criteria $criteria
     * @return void
     *
     * @psalm-return Collection<TKey,T>
     */
    public function matching(Criteria $criteria)
    {
        throw new MethodNotImplementedException('Method matching is not implemented');
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
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @return ClassMetadata
     */
    protected function getClassMetadata()
    {
        return $this->class;
    }
}
