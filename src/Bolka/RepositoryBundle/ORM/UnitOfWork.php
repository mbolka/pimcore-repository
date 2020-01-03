<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM;

use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadataInterface;
use Bolka\RepositoryBundle\ORM\Mapping\ElementMetadataInterface;
use Bolka\RepositoryBundle\ORM\Persisters\ElementPersisterFactory;
use Bolka\RepositoryBundle\ORM\Persisters\PimcoreElementPersisterInterface;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\Internal;
use Doctrine\ORM\ORMInvalidArgumentException;
use InvalidArgumentException;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\AbstractElement;
use Pimcore\Model\FactoryInterface;
use Symfony\Component\Intl\Exception\NotImplementedException;
use UnexpectedValueException;

/**
 * Class UnitOfWork
 * @package Bolka\RepositoryBundle\ORM
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

    /** @var PimcoreElementManagerInterface */
    private $em;
    /** @var PimcoreElementPersisterInterface[] */
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
    /** @var ElementPersisterFactory */
    private $entityPersisterFactory;

    /**
     * UnitOfWork constructor.
     * @param PimcoreElementManagerInterface $entityManager
     * @param FactoryInterface               $factory
     * @param ElementPersisterFactory        $entityPersisterFactory
     */
    public function __construct(
        PimcoreElementManagerInterface $entityManager,
        FactoryInterface $factory,
        ElementPersisterFactory $entityPersisterFactory
    ) {
        $this->em                     = $entityManager;
        $this->modelFactory           = $factory;
        $this->entityPersisterFactory = $entityPersisterFactory;
    }

    /**
     * Gets the EntityPersister for an Entity.
     *
     * @param string $entityName The name of the Entity.
     *
     * @return PimcoreElementPersisterInterface
     */
    public function getEntityPersister($entityName)
    {
        if (isset($this->persisters[$entityName])) {
            return $this->persisters[$entityName];
        }

        $class     = $this->em->getClassMetadata($entityName);
        $persister = $this->entityPersisterFactory->getEntityPersiter($this->em, $class, $this->modelFactory);

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
        $idHash = implode(' ', (array)$sortedId);

        if ($this->identityMap[$rootEntityName][$idHash]) {
            return $this->identityMap[$rootEntityName][$idHash];
        } else {
            return false;
        }
    }

    /**
     * @param AbstractElement $entity
     */
    public function persist(AbstractElement $entity)
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
    public function remove($entity)
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
     * @return void
     */
    public function merge($entity)
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
        if (!isset($this->identityMap[$entityName])) {
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
     * @param object $entity The entity to refresh.*
     * @return void
     *
     * @throws ORMInvalidArgumentException If the entity is not MANAGED.
     */
    public function refresh($entity)
    {
        $class = $this->em->getClassMetadata(get_class($entity));

        if ($this->getEntityState($entity) !== self::STATE_MANAGED) {
            throw ORMInvalidArgumentException::entityNotManaged($entity);
        }

        $this->getEntityPersister($class->getName())->refresh($entity);
    }

    /**
     * @throws ConnectionException
     * @throws \Throwable
     */
    public function commit()
    {
        if (!($this->entityInsertions ||
            $this->entityDeletions ||
            $this->entityUpdates)) {
            return;
        }

        $commitOrder = $this->getCommitOrder();
        $conn        = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            if ($this->entityInsertions) {
                foreach ($commitOrder as $classMetadata) {
                    $this->executeInserts($classMetadata);
                }
            }
            if ($this->entityUpdates) {
                foreach ($commitOrder as $classMetadata) {
                    $this->executeUpdates($classMetadata);
                }
            }
            if ($this->entityDeletions) {
                for ($count = count($commitOrder), $i = $count - 1; $i >= 0 && $this->entityDeletions; --$i) {
                    $this->executeDeletions($commitOrder[$i]);
                }
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
     * @param ElementMetadataInterface $classMetadata
     */
    private function executeInserts(ElementMetadataInterface $classMetadata)
    {
        $className  = $classMetadata->name;
        $persister  = $this->getEntityPersister($className);
        $insertions = [];
        foreach ($this->entityInsertions as $hash => $entity) {
            if (!$entity instanceof $className) {
                continue;
            }
            $insertions[$hash] = $entity;
            $persister->addInsert($entity);
            unset($this->entityInsertions[$hash]);
        }
        $persister->executeInserts();
    }

    /**
     * @param ElementMetadataInterface $classMetadata
     */
    private function executeUpdates(ElementMetadataInterface $classMetadata)
    {
        $className = $classMetadata->name;
        $persister = $this->getEntityPersister($className);
        foreach ($this->entityUpdates as $hash => $entity) {
            if (!$entity instanceof $className) {
                continue;
            }
            $persister->update($entity);
            unset($this->entityUpdates[$hash]);
        }
    }

    /**
     * @param ElementMetadataInterface $classMetadata
     */
    private function executeDeletions(ElementMetadataInterface $classMetadata)
    {
        $className = $classMetadata->name;
        $persister = $this->getEntityPersister($className);

        foreach ($this->entityDeletions as $hash => $entity) {
            if (!$entity instanceof $classMetadata->name) {
                continue;
            }
            $persister->delete($entity);
            unset(
                $this->entityDeletions[$hash],
                $this->entityIdentifiers[$hash],
                $this->entityStates[$hash]
            );
        }
    }

    /**
     * Cleans after commit
     */
    private function postCommitCleanup(): void
    {
        $this->entityInsertions =
        $this->entityUpdates =
        $this->entityDeletions = [];
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForInsert($entity)
    {
        return isset($this->entityInsertions[spl_object_hash($entity)]);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isInIdentityMap($entity)
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
    public function isScheduledForDelete($entity)
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

        /** @var ClassMetadataInterface $class */
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
    private function persistNew($entity)
    {
        $oid                      = spl_object_hash($entity);
        $this->entityStates[$oid] = self::STATE_MANAGED;
        $this->scheduleForInsert($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function addToIdentityMap($entity)
    {
        $classMetadata = $this->em->getClassMetadata(get_class($entity));
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
     * @throws InvalidArgumentException
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
        if (isset($this->originalEntityData[$oid]) && !isset($this->entityInsertions[$oid])) {
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
     * @param AbstractElement $entity
     * @return bool
     */
    public function removeFromIdentityMap(AbstractElement $entity)
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
    public function detach(AbstractElement $entity)
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
     * INTERNAL:
     * Registers an entity as managed.
     *
     * @param AbstractElement $entity The entity.
     * @param array           $id The identifier values.
     *
     * @return void
     */
    public function registerManaged(AbstractElement $entity, array $id)
    {
        $oid = spl_object_hash($entity);

        $this->entityIdentifiers[$oid] = $id;
        $this->entityStates[$oid]      = self::STATE_MANAGED;

        $this->addToIdentityMap($entity);
    }

    /**
     * @param AbstractElement $entity
     */
    private function persistExisted(AbstractElement $entity)
    {
        $oid                       = spl_object_hash($entity);
        $this->entityUpdates[$oid] = $entity;
    }

    /**
     * Gets the CommitOrderCalculator used by the UnitOfWork to order commits.
     *
     * @return \Doctrine\ORM\Internal\CommitOrderCalculator
     */
    public function getCommitOrderCalculator()
    {
        return new Internal\CommitOrderCalculator();
    }

    /**
     * Gets the commit order.
     *
     * @param array|null $entityChangeSet
     *
     * @return array
     */
    private function getCommitOrder(array $entityChangeSet = null)
    {
        if ($entityChangeSet === null) {
            $entityChangeSet = array_merge($this->entityInsertions, $this->entityUpdates, $this->entityDeletions);
        }
        $commitedClasses = $this->getCommitedClasses($entityChangeSet);

        $calc = $this->getCommitOrderCalculator();

        // See if there are any new classes in the changeset, that are not in the
        // commit order graph yet (don't have a node).
        // We have to inspect changeSet to be able to correctly build dependencies.
        $newNodes = [];
        /** @var Concrete $entity */
        foreach ($entityChangeSet as $entity) {
            $class = $this->em->getClassMetadata(get_class($entity));

            if (!$calc->hasNode($class->name)) {
                $calc->addNode($class->name, $class);
            }

            if (!$class->hasRelations()) {
                continue;
            }
            $relatedClasses = $class->getRelatedClasses();

            /**
             * If class has relations but no classes were explicitly defined
             * we have to assume every commited class can be related
             */
            if (empty($relatedClasses)) {
                $relatedClasses = $commitedClasses;
            } else {
                $relatedClasses = array_intersect($relatedClasses, $commitedClasses);
            }
            foreach ($relatedClasses as $relatedClass) {
                $targetClass = $this->em->getClassMetadata($relatedClass);
                if (!$calc->hasNode($targetClass->name)) {
                    $calc->addNode($targetClass->name, $targetClass);
                    $newNodes[] = $targetClass;
                }
                $calc->addDependency($targetClass->name, $class->name, 1);
            }
            $newNodes[] = $class;
        }

        return $calc->sort();
    }

    /**
     * @param array $entitySet
     * @return array
     */
    private function getCommitedClasses(array $entitySet)
    {
        return array_unique(
            array_map(
                function (AbstractElement $entity) {
                    return get_class($entity);
                },
                $entitySet
            )
        );
    }
}
