<?php
/**
 * @category    bosch-stuttgart
 * @date        04/11/2019
 * @author      Michał Bolka <mbolka@divante.co>
 * @copyright   Copyright (c) 2019 Divante Ltd. (https://divante.co)
 */

namespace Bolka\RepositoryBundle\ORM\Persisters;

use Bolka\RepositoryBundle\Common\Collections\Criteria;
use Bolka\RepositoryBundle\ORM\Mapping\ElementMetadataInterface;

/**
 * Interface PimcoreElementPersisterInterface
 * @package Bolka\RepositoryBundle\ORM\Persisters
 */
interface PimcoreElementPersisterInterface
{
    /**
     * @return ElementMetadataInterface
     */
    public function getClassMetadata();

    /**
     * Get all queued inserts.
     *
     * @return array
     */
    public function getInserts();

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
     * @param string $path
     * @return mixed
     */
    public function getByPath(string $path);
}
