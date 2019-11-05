<?php
/**
 * @category    bosch-stuttgart
 * @date        04/11/2019
 * @author      Michał Bolka <mbolka@divante.co>
 * @copyright   Copyright (c) 2019 Divante Ltd. (https://divante.co)
 */

namespace Bolka\RepositoryBundle\ORM\Mapping;

/**
 * Interface ElementMetadataInterface
 * @package Bolka\RepositoryBundle\ORM\Mapping
 */
interface ElementMetadataInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName();

    /**
     * {@inheritDoc}
     */
    public function getIdentifierFieldNames();

    /**
     * Extracts the identifier values of an entity of this class.
     *
     * For composite identifiers, the identifier values are returned as an array
     * with the same order as the field order in {@link identifier}.
     *
     * @param object $entity
     *
     * @return array
     */
    public function getIdentifierValues($entity);

    /**
     * @return bool
     */
    public function hasRelations();

    /**
     * @return string[]
     */
    public function getRelatedClasses();

    public function getTableName();

}
