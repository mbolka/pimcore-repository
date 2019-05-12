<?php
/**
 * @category    tigerspike
 * @date        12/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace RepositoryBundle\Common\Repository;

use RepositoryBundle\Common\PimcoreEntityManagerInterface;

/**
 * Interface RepositoryFactoryInterface
 * @package RepositoryBundle\Common\Repository
 */
interface RepositoryFactoryInterface
{
    /**
     * @param PimcoreEntityManagerInterface $entityManager
     * @param                               $entityName
     * @return mixed
     */
    public function getRepository(PimcoreEntityManagerInterface $entityManager, $entityName);
}
