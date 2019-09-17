<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Persisters\Entity;

use Bolka\RepositoryBundle\Common\Collections\Criteria;
use Pimcore\Model\DataObject\Concrete;
use Bolka\RepositoryBundle\ORM\Mapping\ClassMetadataInterface;

/**
 * Interface PimcoreEntityPersiterInterface
 * @package Bolka\RepositoryBundle\Common\Persisters\Entity
 */
interface PimcoreEntityPersiterInterface
{
    /**
     * @return ClassMetadataInterface
     */
    public function getClassMetadata();

    /**
     * Get all queued inserts.
     *
     * @return array
     */
    public function getInserts();

    /**
     * Adds an entity to the queued insertions.
     * The entity remains queued until {@link executeInserts} is invoked.
     *
     * @param Concrete $entity The entity to queue for insertion.
     *
     * @return void
     */
    public function addInsert(Concrete $entity);

    /**
     * Executes all queued entity insertions and returns any generated post-insert
     * identifiers that were created as a result of the insertions.
     *
     * If no inserts are queued, invoking this method is a NOOP.
     *
     * @return array An array of any generated post-insert IDs. This will be an empty array
     *               if the entity class does not use the IDENTITY generation strategy.
     */
    public function executeInserts();

    /**
     * Updates a managed entity. The entity is updated according to its current changeset
     * in the running UnitOfWork. If there is no changeset, nothing is updated.
     *
     * @param Concrete $entity The entity to update.
     *
     * @return void
     */
    public function update(Concrete $entity);

    /**
     * Deletes a managed entity.
     *
     * The entity to delete must be managed and have a persistent identifier.
     * The deletion happens instantaneously.
     *
     * Subclasses may override this method to customize the semantics of entity deletion.
     *
     * @param Concrete $entity The entity to delete.
     *
     * @return bool TRUE if the entity got deleted in the database, FALSE otherwise.
     */
    public function delete(Concrete $entity);

    /**
     * Count entities (optionally filtered by a criteria)
     *
     * @param  array|Criteria $criteria
     *
     * @return int
     */
    public function count($criteria = []);

    /**
     * Loads an entity by a list of field criteria.
     *
     * @param array      $criteria The criteria by which to load the entity.
     * @param int|null   $limit Limit number of results.
     * @param array|null $orderBy Criteria to order by.
     *
     * @return object|null The loaded and managed entity instance or NULL if the entity can not be found.
     *
     * @todo Check identity map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load(
        array $criteria,
        $limit = null,
        array $orderBy = null
    );

    /**
     * Loads an entity by identifier.
     *
     * @param array       $identifier The entity identifier.
     * @param object|null $entity     The entity to load the data into. If not specified, a new entity is created.
     *
     * @return object The loaded and managed entity instance or NULL if the entity can not be found.
     *
     * @todo Check parameters
     */
    public function loadById(array $identifier, $entity = null);

    /**
     * Refreshes a managed entity.
     *
     * @param Concrete $entity The entity to refresh.
     *                           or NULL if no specific lock mode should be used
     *                           for refreshing the managed entity.
     *
     * @return void
     */
    public function refresh(Concrete $entity);

    /**
     * Loads a list of entities by a list of field criteria.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array
     */
    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null);

    /**
     * Checks whether the given managed entity exists in the database.
     *
     * @param Concrete      $entity
     *
     * @param Criteria|null $extraConditions
     * @return boolean TRUE if the entity exists in the database, FALSE otherwise.
     */
    public function exists(Concrete $entity);

    /**
     * @param string $path
     * @return mixed
     */
    public function getByPath(string $path);

    public function loadCriteria(Criteria $criteria);
}
