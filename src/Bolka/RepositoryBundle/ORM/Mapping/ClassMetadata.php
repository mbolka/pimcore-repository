<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Mapping;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\DataObject\Objectbrick;

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
     * @param string $namespace
     * @param array $identifier
     * @param string|null $repositoryClassName
     */
    public function __construct(
        ClassDefinition $definition,
        string $namespace = 'Pimcore\\Model\\DataObject',
        $identifier = ['o_id'],
        $repositoryClassName = null
    )
    {
        $this->name = $namespace . '\\' . ucfirst($definition->getName());
        $this->namespace = $namespace;
        $this->identifier = $identifier;
        $this->tableName = 'object_' . $definition->getId();
        $this->definition = $definition;
        if (null !== $repositoryClassName) {
            $this->customRepositoryClassName = $repositoryClassName;
        }

        $this->relatedClasses = $this->calculateRelations($definition->getFieldDefinitions());
        /**
         * If calculateRelations() returns null it means there is no relations,
         * otherwise if no classes were defined explicitly we have to consider fact,
         * that all classes can be related into this one.
         */
        $this->hasRelations  = is_array($this->relatedClasses);
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

    /** @var string[] */
    public $relatedClasses = [];

    /** @var bool */
    public $hasRelations = false;

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
            return $this->namespace . '\\' . ucfirst($className);
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

    /**
     * Returns all related classes. If hasRelations returns true, it means it can be related with any Pimcore class.
     *
     * @return string[]
     */
    public function getRelatedClasses()
    {
        return $this->relatedClasses;
    }

    /**
     * @return bool
     */
    public function hasRelations()
    {
        return $this->hasRelations;
    }

    /**
     * Calulates relations between classes
     * @return void
     */
    private function calculateRelations(array $fieldDefinitions)
    {
        $relatedClasses = null;
        $classes = null;
        foreach ($fieldDefinitions as $field) {
            switch (true) {
                case $field->getFieldtype() == 'fieldcollections':
                    $classes = $this->getRelationsFromFC($field);
                    break;
                case $field->getFieldtype() == 'objectbricks':
                    $classes = $this->getRelationsFromOB($field);
                    break;
                case !$field->isRelationType():
                    continue;
                    break;
                case $field->isRelationType():
                    $classes = $field->getClasses();
            }
            if (is_array($classes)) {
                if (empty($classes)) {
                    return [];
                }
                $relatedClasses = [];

                foreach ($classes as $class) {
                    if (is_array($class)) {
                        $className = $class['classes'];
                    } else {
                        $className = $class;
                    }

                    $targetName = $this->fullyQualifiedClassName($className);
                    if (!in_array($targetName, $this->relatedClasses)) {
                        $relatedClasses[] = $targetName;
                    }
                }
            }
        }
        return $relatedClasses;
    }

    /**
     * @param ClassDefinition\Data\Fieldcollections $fieldcollections
     * @return string[]|null
     */
    private function getRelationsFromFC(ClassDefinition\Data\Fieldcollections $fieldcollections)
    {
        $allowedTypes = $fieldcollections->getAllowedTypes();
        foreach ($allowedTypes as $allowedType) {
            $fieldcollection = Fieldcollection\Definition::getByKey($allowedType);
            if ($fieldcollection instanceof Fieldcollection\Definition) {
                return $this->calculateRelations($fieldcollection->getFieldDefinitions());
            }
        }
    }

    /**
     * @param ClassDefinition\Data\Fieldcollections $fieldcollections
     * @return string[]|null
     */
    private function getRelationsFromOB(ClassDefinition\Data\Objectbricks $objectbricks)
    {
        $allowedTypes = $objectbricks->getAllowedTypes();
        foreach ($allowedTypes as $allowedType) {
            $objectbrick = Objectbrick\Definition::getByKey($allowedType);
            if ($objectbrick instanceof Objectbrick\Definition) {
                return $this->calculateRelations($objectbrick->getFieldDefinitions());
            }
        }
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }
}
