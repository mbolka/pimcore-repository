<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM;

use Bolka\RepositoryBundle\ORM\Mapping\ElementMetadataInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Pimcore\Db\Connection;

/**
 * Interface PimcoreEntityManagerInterface
 * @package Bolka\RepositoryBundle\ORM
 */
interface PimcoreElementManagerInterface extends ObjectManager
{
    /**
     * Gets the database connection object used by the EntityManager.
     *
     * @return Connection
     */
    public function getConnection();

    /**
     * Closes the EntityManager. All entities that are currently managed
     * by this EntityManager become detached. The EntityManager may no longer
     * be used after it is closed.
     *
     * @return void
     */
    public function close();

    /**
     * Check if the Entity manager is open or closed.
     *
     * @return bool
     */
    public function isOpen();

    /**
     * Gets the UnitOfWork used by the EntityManager to coordinate operations.
     *
     * @return UnitOfWork
     */
    public function getUnitOfWork();

    /**
     * Returns the ClassMetadata descriptor for a class.
     *
     * The class name must be the fully-qualified class name without a leading backslash
     * (as it is returned by get_class($obj)).
     *
     * @param string $className
     *
     * @return ElementMetadataInterface
     */
    public function getClassMetadata($className);

    /**
     * @return string
     */
    public function getDefaultRepositoryClassName();

    /**
     * Gets the repository for a class.
     *
     * @param string $className
     *
     * @return PimcoreElementRepositoryInterface
     */
    public function getRepository($className);
}
