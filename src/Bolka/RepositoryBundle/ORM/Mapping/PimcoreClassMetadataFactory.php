<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Mapping;

use Pimcore\Model\DataObject\ClassDefinition;

/**
 * Class PimcoreClassMetadataFactory
 * @package Bolka\RepositoryBundle\ORM\Mapping
 */
class PimcoreClassMetadataFactory implements ClassMetadataFactoryInterface
{
    /** @var ClassMetadata[] */
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
     * @return ClassMetadata[] The ClassMetadata instances of all mapped classes.
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
        $className = end(explode('\\', $pimcoreClass));
        if (!array_key_exists($className, $this->metadataClasses)) {
            $definition = ClassDefinition::getById($pimcoreClass::classId());
            $metadata = new ClassMetadata($definition);
            if (array_key_exists($className, $this->customRepositoryClasses)) {
                $metadata->setCustomRepositoryName($this->customRepositoryClasses[$className]);
            }
            $this->metadataClasses[$className] = $metadata;
        }
        return $this->metadataClasses[$className];
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
}
