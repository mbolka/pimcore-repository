<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM;

use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use Pimcore\Db\Connection;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\ORM\Repository\RepositoryFactory;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\FactoryInterface;
use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadataFactoryInterface;
use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadataInterface;
use Bolka\RepositoryBundle\ORM\Repository\RepositoryFactoryInterface;
use Bolka\RepositoryBundle\ORM\Persisters\Entity\EntityPersisterFactory;
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * Class PimcoreEntityManager
 * @package Bolka\RepositoryBundle\ORM
 */
class PimcoreEntityManager implements PimcoreEntityManagerInterface
{

    /**
     * The database connection used by the EntityManager.
     *
     * @var Connection
     */
    private $conn;

    /**
     * The UnitOfWork used to coordinate object-level transactions.
     *
     * @var UnitOfWork
     */
    private $unitOfWork;

    /**
     * Whether the EntityManager is closed or not.
     *
     * @var bool
     */
    private $closed = false;

    /**
     * The event manager that is the central point of the event system.
     *
     * @var EventManager
     */
    private $eventManager;
    /**
     * @var ClassMetadataFactoryInterface
     */
    private $metadataFactory;
    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    private $repositoryClassName = '';

    /**
     * Creates a new EntityManager that operates on the given database connection
     * and uses the given Configuration and EventManager implementations.
     *
     * @param Connection                    $conn
     * @param EventManager                  $eventManager
     * @param FactoryInterface              $factory
     * @param ClassMetadataFactoryInterface $metadataFactory
     * @param RepositoryFactoryInterface    $repositoryFactory
     * @param EntityPersisterFactory        $entityPersisterFactory
     */
    public function __construct(
        Connection $conn,
        EventManager $eventManager,
        FactoryInterface $factory,
        ClassMetadataFactoryInterface $metadataFactory,
        RepositoryFactoryInterface $repositoryFactory,
        EntityPersisterFactory $entityPersisterFactory
    ) {
        $this->conn                = $conn;
        $this->eventManager        = $eventManager;
        $this->repositoryClassName = PimcoreEntityRepository::class;
        $this->metadataFactory     = $metadataFactory;
        $this->repositoryFactory   = $repositoryFactory;
        $this->unitOfWork          = new UnitOfWork($this, $factory, $entityPersisterFactory);
    }

    /**
     * @return string
     */
    public function getDefaultRepositoryClassName()
    {
        return $this->repositoryClassName;
    }
    /**
     * Finds an object by its identifier.
     *
     * This is just a convenient shortcut for getRepository($className)->find($id).
     *
     * @param       $entityName
     * @param mixed $id The identity of the object to find.
     *
     * @return object|null The found object.
     * @throws ORMException
     */
    public function find($entityName, $id)
    {
        /** @var  $class */
        $class = $this->metadataFactory->getMetadataFor(ltrim($entityName, '\\'));
        $identifier = $class->getIdentifierFieldNames()[0];
        if (!is_array($id)) {
            $id = [$identifier => $id];
        }


        if (!isset($id[$identifier])) {
            throw ORMException::missingIdentifierField($class->name, reset(array_keys($id)));
        }

        $unitOfWork = $this->getUnitOfWork();
        $entity = $unitOfWork->tryGetById($class->getIdentifierFieldNames(), $class->name);
        // Check identity map first
        if ($entity !== false) {
            $className = $class->fullyQualifiedClassName($class->name);
            if (!($entity instanceof $className)) {
                return null;
            }

            return $entity;
        }

        $persister = $unitOfWork->getEntityPersister($class->name);
        $object = $persister->loadById($id);
        $this->registerManaged($object, $id);
        return $object;
    }

    /**
     * Throws an exception if the EntityManager is closed or currently not active.
     *
     * @param $entity
     * @return void
     *
     * @throws ORMException If the EntityManager is closed.
     */
    public function persist($entity)
    {
        if (!is_object($entity)) {
            throw ORMInvalidArgumentException::invalidObject('EntityManager#persist()', $entity);
        }

        $this->errorIfClosed();

        $this->unitOfWork->persist($entity);
    }

    /**
     * Removes an object instance.
     *
     * A removed object will be removed from the database as a result of the flush operation.
     *
     * @param object $entity The object instance to remove.
     *
     * @return void
     * @throws ORMException
     */
    public function remove($entity)
    {
        if (!is_object($entity) || !$entity instanceof AbstractObject) {
            throw ORMInvalidArgumentException::invalidObject('EntityManager#remove()', $entity);
        }

        $this->errorIfClosed();
        $this->unitOfWork->remove($entity);

    }

    /**
     * Merges the state of a detached object into the persistence context
     * of this ObjectManager and returns the managed copy of the object.
     * The object passed to merge will not become associated/managed with this ObjectManager.
     *
     * @param object $entity
     *
     * @return object
     * @throws ORMException
     */
    public function merge($entity)
    {
        if (!is_object($entity)) {
            throw ORMInvalidArgumentException::invalidObject('EntityManager#merge()', $entity);
        }

        $this->errorIfClosed();

        return $this->unitOfWork->merge($entity);
    }

    /**
     * Clears the ObjectManager. All objects that are currently managed
     * by this ObjectManager become detached.
     *
     * @param string|null $entityName if given, only entities of this type will get detached
     *
     * @return void
     *
     * @throws ORMInvalidArgumentException                           if a non-null non-string value is given
     *                                                               found in the mappings
     */
    public function clear($entityName = null)
    {
        if (null !== $entityName && ! is_string($entityName)) {
            throw ORMInvalidArgumentException::invalidEntityName($entityName);
        }

        $this->unitOfWork->clear(
            null === $entityName
                ? null
                : $this->metadataFactory->getMetadataFor($entityName)->getName()
        );
    }

    /**
     * Detaches an object from the ObjectManager, causing a managed object to
     * become detached. Unflushed changes made to the object if any
     * (including removal of the object), will not be synchronized to the database.
     * Objects which previously referenced the detached object will continue to
     * reference it.
     *
     * @param object $entity The object to detach.
     *
     * @return void
     */
    public function detach($entity)
    {
        if (!is_object($entity)) {
            throw ORMInvalidArgumentException::invalidObject('EntityManager#detach()', $entity);
        }

        $this->unitOfWork->detach($entity);
    }

    /**
     * Refreshes the persistent state of an object from the database,
     * overriding any local changes that have not yet been persisted.
     *
     * @param $entity
     * @return void
     * @throws ORMException
     */
    public function refresh($entity)
    {
        if (!is_object($entity)) {
            throw ORMInvalidArgumentException::invalidObject('EntityManager#refresh()', $entity);
        }

        $this->errorIfClosed();

        $this->unitOfWork->refresh($entity);
    }

    /**
     * Flushes all changes to objects that have been queued up to now to the database.
     * This effectively synchronizes the in-memory state of managed objects with the
     * database.
     *
     * @return void
     * @throws \Throwable
     */
    public function flush()
    {
        $this->unitOfWork->commit();
    }

    /**
     * Gets the repository for a class.
     *
     * @param $pimcoreClass
     * @return ObjectRepository
     */
    public function getRepository($pimcoreClass)
    {
        return $this->repositoryFactory->getRepository($this, $pimcoreClass);
    }

    /**
     * Returns the ClassMetadata descriptor for a class.
     *
     * The class name must be the fully-qualified class name without a leading backslash
     * (as it is returned by get_class($obj)).
     *
     * @param string $className
     *
     * @return ClassMetadataInterface
     */
    public function getClassMetadata($className)
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    /**
     * Gets the metadata factory used to gather the metadata of classes.
     *
     * @return ClassMetadataFactoryInterface
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * Helper method to initialize a lazy loading proxy or persistent collection.
     *
     * This method is a no-op for other objects.
     *
     * @param object $obj
     *
     * @return void
     */
    public function initializeObject($obj)
    {
        throw new NotImplementedException('Method initializeObject is ont implemented');
    }

    /**
     * Checks if the object is part of the current UnitOfWork and therefore managed.
     *
     * @param object $entity
     *
     * @return bool
     */
    public function contains($entity)
    {
        return $this->unitOfWork->isScheduledForInsert($entity)
            || $this->unitOfWork->isInIdentityMap($entity)
            && !$this->unitOfWork->isScheduledForDelete($entity);
    }

    /**
     * Gets the database connection object used by the EntityManager.
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * Closes the EntityManager. All entities that are currently managed
     * by this EntityManager become detached. The EntityManager may no longer
     * be used after it is closed.
     *
     * @return void
     */
    public function close()
    {
        $this->clear();

        $this->closed = true;
    }

    /**
     * Check if the Entity manager is open or closed.
     *
     * @return bool
     */
    public function isOpen()
    {
        return !$this->closed;
    }

    /**
     * Gets the UnitOfWork used by the EntityManager to coordinate operations.
     *
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    /**
     * Throws an exception if the EntityManager is closed or currently not active.
     *
     * @return void
     *
     * @throws ORMException If the EntityManager is closed.
     */
    private function errorIfClosed()
    {
        if ($this->closed) {
            throw ORMException::entityManagerClosed();
        }
    }

    /**
     * @param       $entity
     * @param array $id
     */
    public function registerManaged($entity, array $id)
    {
        $this->getUnitOfWork()->registerManaged($entity, $id);
    }

    /**
     * Creates a new instance of a SQL query builder.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this);
    }
}
