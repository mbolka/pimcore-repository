<?php
/**
 * @category    pimcore-repository
 * @date        06/06/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\ORMException;
use UnexpectedValueException;

/**
 * Class PimcoreEntityRepository
 * @package Bolka\RepositoryBundle\ORM
 */
interface PimcoreEntityRepositoryInterface extends ObjectRepository, Selectable
{
    /**
     * Clears in-memory objects
     */
    public function clear();

    /**
     * Finds an object by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     *
     * @return object|null The object.
     * @throws ORMException
     */
    public function find($id);

    /**
     * Finds all objects in the repository.
     *
     * @return object[] The objects.
     */
    public function findAll();

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param mixed[]       $criteria
     * @param string[]|null $orderBy
     * @param int|null      $limit
     * @param int|null      $offset
     *
     * @return object[] The objects.
     *
     * @throws UnexpectedValueException
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null);

    /**
     * Finds a single object by a set of criteria.
     *
     * @param mixed[]    $criteria The criteria.
     *
     * @param array|null $orderBy
     * @return object|null The object.
     */
    public function findOneBy(array $criteria, array $orderBy = null);

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @return string
     */
    public function getClassName();

    /**
     * Selects all elements from a selectable that match the expression and
     * returns a new collection containing these elements.
     *
     * @param Criteria $criteria
     *
     * @psalm-return Collection<TKey,T>
     * @return LazyCriteriaCollection
     */
    public function matching(Criteria $criteria);

    /**
     * @param array $criteria
     * @return int
     */
    public function count(array $criteria);
}
