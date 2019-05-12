<?php
/**
 * @category    tigerspike
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace RepositoryBundle\Common;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\UnitOfWork;
use Pimcore\Db\Connection;
use RepositoryBundle\Common\Persistence\Mapping\ClassMetadata;

/**
 * Interface PimcoreEntityManagerInterface
 * @package RepositoryBundle\ORM
 */
interface PimcoreEntityManagerInterface extends ObjectManager
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
     * @return ClassMetadata
     */
    public function getClassMetadata($className);

    /**
     * @return string
     */
    public function getDefaultRepositoryClassName();
}
