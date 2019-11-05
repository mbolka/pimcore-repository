<?php
/**
 * @category    pimcore-repository
 * @date        12/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Repository;

use Bolka\RepositoryBundle\ORM\PimcoreElementManagerInterface;

/**
 * Interface RepositoryFactoryInterface
 * @package Bolka\RepositoryBundle\Common\Repository
 */
interface RepositoryFactoryInterface
{
    /**
     * @param PimcoreElementManagerInterface $entityManager
     * @param string                         $pimcoreClass
     * @return mixed
     */
    public function getRepository(PimcoreElementManagerInterface $entityManager, string $pimcoreClass);
}
