<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Persisters\Entity;

use Bolka\RepositoryBundle\Common\Collections\Criteria;
use Bolka\RepositoryBundle\ORM\Persisters\PimcoreElementPersisterInterface;
use Pimcore\Model\DataObject\Concrete;

/**
 * Interface PimcoreEntityPersiterInterface
 * @package Bolka\RepositoryBundle\Common\Persisters\Entity
 */
interface PimcoreEntityPersisterInterface extends PimcoreElementPersisterInterface
{
    /**
     * @param Criteria $criteria
     * @return mixed
     */
    public function loadCriteria(Criteria $criteria);

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
     * Checks whether the given managed entity exists in the database.
     *
     * @param Concrete      $entity
     *
     * @return boolean TRUE if the entity exists in the database, FALSE otherwise.
     */
    public function exists(Concrete $entity);
}
