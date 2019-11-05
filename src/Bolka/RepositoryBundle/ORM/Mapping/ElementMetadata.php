<?php
/**
 * @category    bosch-stuttgart
 * @date        04/11/2019
 * @author      Michał Bolka <mbolka@divante.co>
 * @copyright   Copyright (c) 2019 Divante Ltd. (https://divante.co)
 */

namespace Bolka\RepositoryBundle\ORM\Mapping;

/**
 * Class ElementMetadata
 * @package Bolka\RepositoryBundle\ORM\Mapping
 */
class ElementMetadata implements ElementMetadataInterface
{
    /**
     * ClassMetadata constructor.
     * @param string      $elementClassName
     * @param array       $identifier
     * @param string      $tableName
     * @param string|null $repositoryClassName
     */
    public function __construct(
        string $elementClassName,
        $identifier = ['o_id'],
        string $tableName,
        $repositoryClassName = null
    )
    {
        $this->name = $elementClassName;
        $namepsaceArray = explode('\\', $elementClassName);
        $this->className = end($namepsaceArray);
        $this->identifier = $identifier;
        $this->tableName = $tableName;
        if (null !== $repositoryClassName) {
            $this->customRepositoryClassName = $repositoryClassName;
        }
    }

    /** @var string */
    public $className;

    /**
     * READ-ONLY: The name of the entity class.
     *
     * @var string
     */
    public $name;

    public $tableName;

    /** @var array */
    public $identifier = [];

    public $customRepositoryClassName = '';

    /** @var bool */
    public $hasRelations = false;

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierFieldNames()
    {
        return $this->identifier;
    }

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
    public function getIdentifierValues($entity)
    {
        $idName = $this->identifier[0];
        $value = $entity->get($idName, $entity);

        if (null === $value) {
            return [];
        }

        return [$idName => $value];
    }

    /**
     * @param string $name
     */
    public function setCustomRepositoryName(string $name)
    {
        $this->customRepositoryClassName = $name;
    }

    /**
     * @return bool
     */
    public function hasRelations()
    {
        return $this->hasRelations;
    }

    /**
     * @return string[]
     */
    public function getRelatedClasses()
    {
        return [];
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }
}
