<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Mapping;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document;
use Pimcore\Model\Element\AbstractElement;

/**
 * Class PimcoreClassMetadataFactory
 * @package Bolka\RepositoryBundle\ORM\Mapping
 */
class PimcoreElementMetadataFactory implements PimcoreElementMetadataFactoryInterface
{
    /** @var ElementMetadataInterface[] */
    private $metadataClasses = [];

    /** @var string[] */
    private $customRepositoryClasses = [];

    /**
     * @param string $repositoryClass
     * @param string $className
     */
    public function addCustomRepository(string $repositoryClass, string $className)
    {
        $this->customRepositoryClasses[$className] = $repositoryClass;
    }

    /**
     * Forces the factory to load the metadata of all classes known to the underlying
     * mapping driver.
     *
     * @return ElementMetadataInterface[] The metadata instances of all mapped elements.
     */
    public function getAllMetadata()
    {
        return $this->metadataClasses;
    }

    /**
     * Gets the class metadata descriptor for a class.
     *
     * @param $pimcoreClass
     * @return ClassMetadataInterface
     * @throws \Exception
     */
    public function getMetadataFor($pimcoreClass)
    {
        $reflection = new \ReflectionClass($pimcoreClass);
        if ($this->hasMetadataFor($pimcoreClass)) {
            return $this->metadataClasses[$pimcoreClass];
        }
        if ($reflection->isSubclassOf(Concrete::class)) {
            $definition = ClassDefinition::getById($pimcoreClass::classId());
            $metadata   = new ClassMetadata($definition);
            if (array_key_exists($pimcoreClass, $this->customRepositoryClasses)) {
                $metadata->setCustomRepositoryName($this->customRepositoryClasses[$pimcoreClass]);
            }
            $this->metadataClasses[$pimcoreClass] = $metadata;
        } elseif ($reflection->isSubclassOf(AbstractElement::class)) {
            $tableName = $this->getTableName($reflection);
            $metadata = new ElementMetadata($pimcoreClass, ['id'], $tableName);
            if (array_key_exists($pimcoreClass, $this->customRepositoryClasses)) {
                $metadata->setCustomRepositoryName($this->customRepositoryClasses[$pimcoreClass]);
            }
            $this->metadataClasses[$pimcoreClass] = $metadata;
        }
        return $this->metadataClasses[$pimcoreClass];
    }

    /**
     * Checks whether the factory has the metadata for a class loaded already.
     *
     * @param string $className
     *
     * @return bool TRUE if the metadata of the class in question is already loaded, FALSE otherwise.
     */
    public function hasMetadataFor($className)
    {
        return array_key_exists($className, $this->metadataClasses);
    }

    /**
     * Sets the metadata descriptor for a specific class.
     *
     * @param string        $className
     * @param ClassMetadata $class
     */
    public function setMetadataFor($className, $class)
    {
        $this->metadataClasses[$className] = $class;
    }

    /**
     * Returns whether the class with the specified name should have its metadata loaded.
     * This is only the case if it is either mapped directly or as a MappedSuperclass.
     *
     * @param string $className
     *
     * @return bool
     */
    public function isTransient($className)
    {
        return false;
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @return string
     */
    private function getTableName(\ReflectionClass $reflectionClass)
    {
        switch (true) {
            case $reflectionClass->getName() == Document::class || $reflectionClass->isSubclassOf(Document::class):
                return 'documents';
            case $reflectionClass->getName() == Asset::class || $reflectionClass->isSubclassOf(Asset::class):
                return 'assets';
            default:
                throw new \RuntimeException(sprintf('Repository does not support class: %s', $reflectionClass));
        }
    }
}
