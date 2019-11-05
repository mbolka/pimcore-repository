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
interface ClassMetadataInterface extends ElementMetadataInterface
{
    /**
     * @param string|null $className
     *
     * @return string|null null if the input value is null
     */
    public function fullyQualifiedClassName($className);

    /**
     * @return ClassDefinition
     */
    public function getDefinition();
}
