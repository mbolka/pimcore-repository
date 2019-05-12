<?php
/**
 * @category    tigerspike
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace RepositoryBundle\ORM;

use Doctrine\ORM\ORMInvalidArgumentException;
use InvalidArgumentException;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\FactoryInterface;
use RepositoryBundle\Common\PimcoreEntityManagerInterface;
use RepositoryBundle\ORM\Mapping\ClassMetadata;
use RepositoryBundle\Common\Persisters\Entity\PimcoreEntityPersiterInterface;
use RepositoryBundle\ORM\Persisters\Entity\BasicPimcoreEntityPersister;
use Symfony\Component\Intl\Exception\NotImplementedException;
use UnexpectedValueException;

/**
 * Class UnitOfWork
 * @package RepositoryBundle\ORM
 */
class UnitOfWork
{
    /**
     * An entity is in MANAGED state when its persistence is managed by an EntityManager.
     */
    const STATE_MANAGED = 1;

    /**
     * An entity is new if it has just been instantiated (i.e. using the "new" operator)
     * and is not (yet) managed by an EntityManager.
     */
    const STATE_NEW = 2;

    /**
     * A detached entity is an instance with persistent state and identity that is not
     * (or no longer) associated with an EntityManager (and a UnitOfWork).
     */
    const STATE_DETACHED = 3;

    /**
     * A removed entity instance is an instance with a persistent identity,
     * associated with an EntityManager, whose persistent state will be deleted
     * on commit.
     */
    const STATE_REMOVED = 4;

    /** @var PimcoreEntityManagerInterface */
    private $em;
    /** @var PimcoreEntityPersiterInterface[] */
    private $persisters = [];
    /** @var array */
    private $identityMap = [];
    /** @var array */
    private $entityStates = [];
    /** @var array */
    private $entityDeletions = [];
    /** @var array */
    private $entityIdentifiers = [];
    /** @var array */
    private $entityInsertions = [];
    /** @var array */
    private $entityUpdates = [];
    /** @var FactoryInterface */
    private $modelFactory;
    /** @var array */
    private $originalEntityData = [];

    /**
     * UnitOfWork constructor.
     * @param PimcoreEntityManagerInterface $entityManager
     * @param FactoryInterface              $factory
     */
    public function __construct(PimcoreEntityManagerInterface $entityManager, FactoryInterface $factory)
    {
        $this->em = $entityManager;
        $this->modelFactory = $factory;
    }

    /**
     * Gets the EntityPersister for an Entity.
     *
     * @param string $entityName The name of the Entity.
     *
     * @return PimcoreEntityPersiterInterface
     */
    public function getEntityPersister($entityName)
    {
        if (isset($this->persisters[$entityName])) {
            return $this->persisters[$entityName];
        }

        $class = $this->em->getClassMetadata($entityName);
        $persister = new BasicPimcoreEntityPersister($this->em, $class, $this->modelFactory);

        $this->persisters[$entityName] = $persister;

        return $this->persisters[$entityName];
    }

    /**
     * @param array $sortedId
     * @param       $rootEntityName
     * @return bool|object
     */
    public function tryGetById(array $sortedId, $rootEntityName)
    {
        $idHash = implode(' ', (array) $sortedId);

        if ($this->identityMap[$rootEntityName][$idHash]) {
            return $this->identityMap[$rootEntityName][$idHash];
        } else {
            return false;
        }
    }

    /**
     * @param object $entity
     */
    public function persist(object $entity)
    {
        $oid = spl_object_hash($entity);

        // We assume NEW, so DETACHED entities result in an exception on flush (constraint violation).
        // If we would detect DETACHED here we would throw an exception anyway with the same
        // consequences (not recoverable/programming error), so just assuming NEW here
        // lets us avoid some database lookups for entities with natural identifiers.
        $entityState = $this->getEntityState($entity, self::STATE_NEW);

        switch ($entityState) {
            case self::STATE_MANAGED:
                $this->persistExisted($entity);
                //TODO Implement dirty check
                break;

            case self::STATE_NEW:
                $this->persistNew($entity);
                break;

            case self::STATE_REMOVED:
                // Entity becomes managed again
                unset($this->entityDeletions[$oid]);
                $this->addToIdentityMap($entity);
                $this->entityStates[$oid] = self::STATE_MANAGED;
                break;

            case self::STATE_DETACHED:
                throw ORMInvalidArgumentException::detachedEntityCannot($entity, "persisted");

            default:
                throw new UnexpectedValueException("Unexpected entity state: $entityState." . self::objToStr($entity));
        }
    }

    /**
     * @param object $entity
     */
    public function remove(object $entity)
    {
        $entityState = $this->getEntityState($entity);

        switch ($entityState) {
            case self::STATE_NEW:
            case self::STATE_REMOVED:
                break;
            case self::STATE_MANAGED:
                $this->scheduleForDelete($entity);
                break;
            case self::STATE_DETACHED:
                throw ORMInvalidArgumentException::detachedEntityCannot($entity, "removed");
            default:
                throw new UnexpectedValueException("Unexpected entity state: $entityState." . self::objToStr($entity));
        }
    }

    /**
     * @param object $entity
     * @return object
     */
    public function merge(object $entity)
    {
        throw new NotImplementedException('Method merge is not implemented');
    }

    /**
     * @param null $entityName
     */
    public function clear($entityName = null)
    {
        if ($entityName === null) {
            $this->identityMap =
            $this->entityIdentifiers =
            $this->entityStates =
            $this->entityInsertions =
            $this->entityUpdates =
            $this->entityDeletions = [];
        } else {
            $this->clearIdentityMapForEntityName($entityName);
            $this->clearEntityInsertionsForEntityName($entityName);
        }
    }

    /**
     * @param string $entityName
     */
    private function clearIdentityMapForEntityName($entityName)
    {
        if (! isset($this->identityMap[$entityName])) {
            return;
        }

        foreach ($this->identityMap[$entityName] as $entity) {
            $this->detach($entity);
        }
    }

    /**
     * @param string $entityName
     */
    private function clearEntityInsertionsForEntityName($entityName)
    {
        foreach ($this->entityInsertions as $hash => $entity) {
            // note: performance optimization - `instanceof` is much faster than a function call
            if ($entity instanceof $entityName && get_class($entity) === $entityName) {
                unset($this->entityInsertions[$hash]);
            }
        }
    }

    /**
     * Executes a refresh operation on an entity.
     *
     * @param object $entity  The entity to refresh.*
     * @return void
     *
     * @throws ORMInvalidArgumentException If the entity is not MANAGED.
     */
    public function refresh($entity)
    {
        $class = $this->em->getClassMetadata($entity->getClassName());

        if ($this->getEntityState($entity) !== self::STATE_MANAGED) {
            throw ORMInvalidArgumentException::entityNotManaged($entity);
        }

        $this->getEntityPersister($class->getName())->refresh($entity);
    }

    /**
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Throwable
     */
    public function commit()
    {
        if (!($this->entityInsertions ||
            $this->entityDeletions ||
            $this->entityUpdates)) {
            return;
        }


        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            foreach ($this->entityInsertions as $hash => $entityInsertion) {
                $this->executeSave($entityInsertion);
            }
            foreach ($this->entityUpdates as $hash => $entityUpdate) {
                $this->executeSave($entityUpdate);
            }
            foreach ($this->entityDeletions as $hash => $entityDeletion) {
                $this->executeDelete($entityDeletion);
            }

        } catch (\Throwable $e) {
            $this->em->close();
            $conn->rollBack();

            throw $e;
        }
        $conn->commit();


        $this->postCommitCleanup();
    }


    /**
     * Cleans after commit
     */
    private function postCommitCleanup() : void
    {
        $this->entityInsertions =
        $this->entityUpdates =
        $this->entityDeletions = [];
    }

    /**
     * @param object $obj
     */
    public function initializeObject(object $obj)
    {
        throw new NotImplementedException('Method intizlieObject is not implemented');
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForInsert(object $entity)
    {
        return isset($this->entityInsertions[spl_object_hash($entity)]);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isInIdentityMap(object $entity)
    {
        $oid = spl_object_hash($entity);

        if (empty($this->entityIdentifiers[$oid])) {
            return false;
        }

        $classMetadata = $this->em->getClassMetadata(get_class($entity));
        $idHash        = implode(' ', $this->entityIdentifiers[$oid]);

        return isset($this->identityMap[$classMetadata->getName()][$idHash]);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForDelete(object $entity)
    {
        return isset($this->entityDeletions[spl_object_hash($entity)]);
    }

    /**
     * Gets the state of an entity with regard to the current unit of work.
     *
     * @param object   $entity
     * @param int|null $assume The state to assume if the state is not yet known (not MANAGED or REMOVED).
     *                         This parameter can be set to improve performance of entity state detection
     *                         by potentially avoiding a database lookup if the distinction between NEW and DETACHED
     *                         is either known or does not matter for the caller of the method.
     *
     * @return int The entity state.
     */
    public function getEntityState($entity, $assume = null)
    {
        $oid = spl_object_hash($entity);

        if (isset($this->entityStates[$oid])) {
            return $this->entityStates[$oid];
        }

        if ($assume !== null) {
            return $assume;
        }

        /** @var ClassMetadata $class */
        $class = $this->em->getClassMetadata(get_class($entity));
        $id    = $class->getIdentifierValues($entity);

        if (!$id) {
            return self::STATE_NEW;
        }

        if ($this->tryGetById($id, $class->name)) {
            return self::STATE_DETACHED;
        }

        if ($this->getEntityPersister($class->name)->exists($entity)) {
            return self::STATE_DETACHED;
        }

        return self::STATE_NEW;
    }

    /**
     * @param object $entity
     */
    private function persistNew(object $entity)
    {
        $oid    = spl_object_hash($entity);
        $this->entityStates[$oid] = self::STATE_MANAGED;
        $this->scheduleForInsert($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function addToIdentityMap(object $entity)
    {
        $classMetadata = $this->em->getClassMetadata($entity->getClassName());
        $identifier    = $this->entityIdentifiers[spl_object_hash($entity)];

        if (empty($identifier) || in_array(null, $identifier, true)) {
            throw ORMInvalidArgumentException::entityWithoutIdentity($classMetadata->name, $entity);
        }

        $idHash    = implode(' ', $identifier);
        $className = $classMetadata->getName();

        if (isset($this->identityMap[$className][$idHash])) {
            return false;
        }

        $this->identityMap[$className][$idHash] = $entity;

        return true;
    }

    /**
     * Schedules an entity for insertion into the database.
     * If the entity already has an identifier, it will be added to the identity map.
     *
     * @param object $entity The entity to schedule for insertion.
     *
     * @return void
     *
     * @throws ORMInvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function scheduleForInsert($entity)
    {
        $oid = spl_object_hash($entity);

        if (isset($this->entityUpdates[$oid])) {
            throw new InvalidArgumentException("Dirty entity can not be scheduled for insertion.");
        }

        if (isset($this->entityDeletions[$oid])) {
            throw ORMInvalidArgumentException::scheduleInsertForRemovedEntity($entity);
        }
        if (isset($this->originalEntityData[$oid]) && ! isset($this->entityInsertions[$oid])) {
            throw ORMInvalidArgumentException::scheduleInsertForManagedEntity($entity);
        }

        if (isset($this->entityInsertions[$oid])) {
            throw ORMInvalidArgumentException::scheduleInsertTwice($entity);
        }

        $this->entityInsertions[$oid] = $entity;

        if (isset($this->entityIdentifiers[$oid])) {
            $this->addToIdentityMap($entity);
        }
    }

    /**
     * INTERNAL:
     * Schedules an entity for deletion.
     *
     * @param object $entity
     *
     * @return void
     */
    public function scheduleForDelete($entity)
    {
        $oid = spl_object_hash($entity);

        if (isset($this->entityInsertions[$oid])) {
            if ($this->isInIdentityMap($entity)) {
                $this->removeFromIdentityMap($entity);
            }
            unset($this->entityInsertions[$oid], $this->entityStates[$oid]);
            return;
        }

        if (!$this->isInIdentityMap($entity)) {
            return;
        }

        $this->removeFromIdentityMap($entity);

        unset($this->entityUpdates[$oid]);

        if (!isset($this->entityDeletions[$oid])) {
            $this->entityDeletions[$oid] = $entity;
            $this->entityStates[$oid]    = self::STATE_REMOVED;
        }
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function removeFromIdentityMap(object $entity)
    {
        $oid           = spl_object_hash($entity);
        $classMetadata = $this->em->getClassMetadata(get_class($entity));
        $idHash        = implode(' ', $this->entityIdentifiers[$oid]);

        if ($idHash === '') {
            throw ORMInvalidArgumentException::entityHasNoIdentity($entity, "remove from identity map");
        }

        $className = $classMetadata->getName();

        if (isset($this->identityMap[$className][$idHash])) {
            unset($this->identityMap[$className][$idHash]);
            return true;
        }

        return false;
    }

    /**
     * Executes a detach operation on the given entity.
     *
     * @param object $entity
     * @return void
     */
    public function detach($entity)
    {
        $oid = spl_object_hash($entity);

        switch ($this->getEntityState($entity, self::STATE_DETACHED)) {
            case self::STATE_MANAGED:
                if ($this->isInIdentityMap($entity)) {
                    $this->removeFromIdentityMap($entity);
                }

                unset(
                    $this->entityInsertions[$oid],
                    $this->entityUpdates[$oid],
                    $this->entityDeletions[$oid],
                    $this->entityIdentifiers[$oid],
                    $this->entityStates[$oid]
                );
                break;
            case self::STATE_NEW:
            case self::STATE_DETACHED:
            default:
                return;

        }
    }

    /**
     * @param $entity
     * @throws \Exception
     */
    private function executeSave($entity)
    {
        if (!$entity instanceof AbstractObject) {
            throw new InvalidArgumentException(
                sprintf('Argument of class %s is not supported', get_class($entity))
            );
        }
        if ($entity instanceof Concrete && !$entity->isPublished()) {
            $entity->setOmitMandatoryCheck(true);
        }
        $entity->save();
    }

    /**
     * @param $entityDeletion
     * @throws \Exception
     */
    private function executeDelete($entityDeletion)
    {
        if (!$entityDeletion instanceof AbstractObject) {
            throw new InvalidArgumentException(
                sprintf('Argument of class %s is not supported', get_class($entityDeletion))
            );
        }
        $entityDeletion->delete();
    }

    /**
     * INTERNAL:
     * Registers an entity as managed.
     *
     * @param object $entity The entity.
     * @param array  $id     The identifier values.
     *
     * @return void
     */
    public function registerManaged($entity, array $id)
    {
        $oid = spl_object_hash($entity);

        $this->entityIdentifiers[$oid]  = $id;
        $this->entityStates[$oid]       = self::STATE_MANAGED;

        $this->addToIdentityMap($entity);
    }

    /**
     * @param object $entity
     */
    private function persistExisted(object $entity)
    {
        $oid = spl_object_hash($entity);
        $this->entityUpdates[$oid] = $entity;
    }
}
