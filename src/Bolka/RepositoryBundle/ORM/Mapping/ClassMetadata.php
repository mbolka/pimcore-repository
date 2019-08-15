<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Mapping;

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * Class ClassMetadata
 * @package Bolka\RepositoryBundle\ORM\Mapping
 */
class ClassMetadata implements ClassMetadataInterface
{
    /**
     * @var ClassDefinition
     */
    private $definition;

    /**
     * ClassMetadata constructor.
     * @param ClassDefinition $definition
     * @param string          $namespace
     * @param array           $identifier
     * @param string|null     $repositoryClassName
     */
    public function __construct(
        ClassDefinition $definition,
        string $namespace = 'Pimcore\\Model\\DataObject',
        $identifier = ['o_id'],
        $repositoryClassName = null
    ) {
        $this->name = $namespace . '\\' . ucfirst($definition->getName());
        $this->namespace = $namespace;
        $this->identifier = $identifier;
        $this->tableName = 'object_' . $definition->getId();
        $this->definition = $definition;
        if (null !== $repositoryClassName) {
            $this->customRepositoryClassName = $repositoryClassName;
        }
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

    /** @var array */
    public $identifier = [];

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

    /**
     * @return ClassDefinition
     */
    public function getDefinition()
    {
        return $this->definition;
    }
}
