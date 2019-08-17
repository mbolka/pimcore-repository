<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Mapping;

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * Class ClassMetadataInterface
 * @package Bolka\RepositoryBundle\Common\Persistence\Mapping
 * @property string $name
 */
interface ClassMetadataInterface
{
    /**
     * @param  string|null $className
     *
     * @return string|null null if the input value is null
     */
    public function fullyQualifiedClassName($className);

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
     * @return ClassDefinition
     */
    public function getDefinition();

    /**
     * @return string[]
     */
    public function getRelatedClasses();

    /**
     * @return bool
     */
    public function hasRelations();
}
