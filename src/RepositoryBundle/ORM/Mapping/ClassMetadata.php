<?php
/**
 * @category    tigerspike
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace RepositoryBundle\ORM\Mapping;

/**
 * Class ClassMetadata
 * @package RepositoryBundle\ORM\Mapping
 */
class ClassMetadata implements \RepositoryBundle\Common\Persistence\Mapping\ClassMetadata
{

    /**
     * ClassMetadata constructor.
     * @param string $className
     * @param int    $classId
     * @param string $namespace
     * @param string $identifier
     */
    public function __construct(
        string $className,
        int $classId,
        string $namespace ='Pimcore\\Model\\DataObject',
        $identifier = ['id']
    ) {
        $this->name = $className;
        $this->namespace = $namespace;
        $this->identifier = $identifier;
        $this->tableName = 'object_' . $classId;
    }

    /**
     * READ-ONLY: The name of the entity class.
     *
     * @var string
     */
    public $name;

    /**
     * READ-ONLY: The namespace the entity class is contained in.
     *
     * @var string
     *
     */
    public $namespace;

    public $tableName;

    public $identifier = ['id'];

    public $customRepositoryClassName = '';
    /**
     * @param  string|null $className
     *
     * @return string|null null if the input value is null
     */
    public function fullyQualifiedClassName($className)
    {
        if (empty($className)) {
            return $className;
        }

        if ($className !== null && strpos($className, '\\') === false && $this->namespace) {
            return $this->namespace . '\\' . $className;
        }

        return $className;
    }

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
}
