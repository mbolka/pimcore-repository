<?php
/**
 * @category    bosch-stuttgart
 * @date        04/11/2019
 * @author      Michał Bolka <mbolka@divante.co>
 * @copyright   Copyright (c) 2019 Divante Ltd. (https://divante.co)
 */

namespace Bolka\RepositoryBundle\ORM\Persisters\Document;

use Bolka\RepositoryBundle\ORM\Persisters\PimcoreElementPersisterInterface;
use Pimcore\Model\Element\AbstractElement;

/**
 * Interface PimcoreAbstractElementPersisterInterface
 * @package Bolka\RepositoryBundle\ORM\Persisters\Document
 */
interface PimcoreAbstractElementPersisterInterface extends PimcoreElementPersisterInterface
{
    /**
     * Adds an entity to the queued insertions.
     * The entity remains queued until {@link executeInserts} is invoked.
     *
     * @param AbstractElement $entity The entity to queue for insertion.
     *
     * @return void
     */
    public function addInsert(AbstractElement $entity);

    /**
     * Updates a managed entity. The entity is updated according to its current changeset
     * in the running UnitOfWork. If there is no changeset, nothing is updated.
     *
     * @param AbstractElement $entity The entity to update.
     *
     * @return void
     */
    public function update(AbstractElement $entity);

    /**
     * Deletes a managed entity.
     *
     * The entity to delete must be managed and have a persistent identifier.
     * The deletion happens instantaneously.
     *
     * Subclasses may override this method to customize the semantics of entity deletion.
     *
     * @param AbstractElement $entity The entity to delete.
     *
     * @return bool TRUE if the entity got deleted in the database, FALSE otherwise.
     */
    public function delete(AbstractElement $entity);

    /**
     * Refreshes a managed entity.
     *
     * @param AbstractElement $entity The entity to refresh.
     *                           or NULL if no specific lock mode should be used
     *                           for refreshing the managed entity.
     *
     * @return void
     */
    public function refresh(AbstractElement $entity);

    /**
     * Checks whether the given managed entity exists in the database.
     *
     * @param AbstractElement $entity
     *
     * @return boolean TRUE if the entity exists in the database, FALSE otherwise.
     */
    public function exists(AbstractElement $entity);
}
