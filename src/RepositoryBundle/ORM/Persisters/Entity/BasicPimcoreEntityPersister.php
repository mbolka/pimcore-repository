<?php
/**
 * @category    tigerspike
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace RepositoryBundle\ORM\Persisters\Entity;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\DBALException;
use Pimcore\Db\Connection;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\FactoryInterface;
use Pimcore\Model\Listing\AbstractListing;
use RepositoryBundle\Common\Persisters\Entity\PimcoreEntityPersiterInterface;
use RepositoryBundle\ORM\Mapping\ClassMetadata;
use RepositoryBundle\Common\PimcoreEntityManagerInterface;

/**
 * Class BasicPimcoreEntityPersister
 * @package RepositoryBundle\ORM\Persisters\Entity
 */
class BasicPimcoreEntityPersister implements PimcoreEntityPersiterInterface
{
    /**
     * @var PimcoreEntityManagerInterface
     */
    private $em;
    /**
     * @var ClassMetadata
     */
    private $class;

    /**
     * Queued inserts.
     *
     * @var array
     */
    protected $queuedInserts = [];
    /**
     * @var FactoryInterface
     */
    private $modelFactory;

    /** @var AbstractListing */
    private $listing;
    /** @var Connection */
    private $connection;


    /**
     * BasicPimcoreEntityPersister constructor.
     * @param PimcoreEntityManagerInterface $em
     * @param ClassMetadata                 $class
     * @param FactoryInterface              $modelFactory
     */
    public function __construct(
        PimcoreEntityManagerInterface $em,
        ClassMetadata $class,
        FactoryInterface $modelFactory
    ) {
        $this->em    = $em;
        $this->class = $class;
        $this->modelFactory = $modelFactory;
        $this->connection = $em->getConnection();
    }

    /**
     * @return ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->class;
    }

    /**
     * Get all queued inserts.
     *
     * @return array
     */
    public function getInserts()
    {
        return $this->queuedInserts;
    }

    /**
     * Adds an entity to the queued insertions.
     * The entity remains queued until {@link executeInserts} is invoked.
     *
     * @param Concrete $entity The entity to queue for insertion.
     *
     * @return void
     */
    public function addInsert(Concrete $entity)
    {
        $this->queuedInserts[spl_object_hash($entity)] = $entity;
    }

    /**
     * Executes all queued entity insertions and returns any generated post-insert
     * identifiers that were created as a result of the insertions.
     *
     * If no inserts are queued, invoking this method is a NOOP.
     *
     * @return array An array of any generated post-insert IDs. This will be an empty array
     *               if the entity class does not use the IDENTITY generation strategy.
     * @throws \Exception
     */
    public function executeInserts()
    {
        $insertedIds = [];
        foreach ($this->queuedInserts as $insert) {
            if (!$insert->getPublished()) {
                $insert->setOmitMandatoryCheck(true);
            }
            $insert->save();
            $insertedIds[] = $insert->getId();
        }
        $this->queuedInserts = [];
        return $insertedIds;
    }

    /**
     * Updates a managed entity. The entity is updated according to its current changeset
     * in the running UnitOfWork. If there is no changeset, nothing is updated.
     *
     * @param Concrete $entity The entity to update.
     *
     * @return void
     * @throws \Exception
     */
    public function update(Concrete $entity)
    {
        if (!$entity->getPublished()) {
            $entity->setOmitMandatoryCheck(true);
        }
        $entity->save();
    }

    /**
     * Deletes a managed entity.
     *
     * The entity to delete must be managed and have a persistent identifier.
     * The deletion happens instantaneously.
     *
     * Subclasses may override this method to customize the semantics of entity deletion.
     *
     * @param Concrete $entity The entity to delete.
     *
     * @return void TRUE if the entity got deleted in the database, FALSE otherwise.
     * @throws \Exception
     */
    public function delete(Concrete $entity)
    {
        $entity->delete();
    }

    /**
     * Count entities (optionally filtered by a criteria)
     *
     * @param array|Criteria $criteria
     *
     * @return int
     */
    public function count($criteria = [])
    {
        $className = $this->getFullQualifiedClassName();
        $listClass = $className . '\\Listing';
        $list = $this->modelFactory->build($listClass);
        $list->setValues($criteria);
        return $list->getTotalCount();
    }

    /**
     * Loads an entity by a list of field criteria.
     *
     * @param array      $criteria The criteria by which to load the entity.
     * @param int|null   $limit Limit number of results.
     * @param array|null $orderBy Criteria to order by.
     *
     * @return object|null The loaded and managed entity instance or NULL if the entity can not be found.
     *
     * @todo Check identity map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load(
        array $criteria,
        $limit = null,
        array $orderBy = null
    ) {
        $entities = $this->loadAll($criteria, $orderBy, $limit, 0);
        return $entities ? $entities[0] : null;
    }

    /**
     * Loads an entity by identifier.
     *
     * @param array       $identifier The entity identifier.
     * @param object|null $entity The entity to load the data into. If not specified, a new entity is created.
     *
     * @return object The loaded and managed entity instance or NULL if the entity can not be found.
     *
     * @todo Check parameters
     */
    public function loadById(array $identifier, $entity = null)
    {
        return $this->load($identifier, $entity);
    }

    /**
     * Refreshes a managed entity.
     *
     * @param object $entity The entity to refresh.
     * @return void
     */
    public function refresh($entity)
    {
        $obj = AbstractObject::getById($entity->getId(), true);
        $vars = $obj->getObjectVars();
        foreach ($vars as $key => $var) {
            $entity->setObjectVar($key, $var);
        }
    }

    /**
     * Loads a list of entities by a list of field criteria.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array
     */
    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $list = $this->getListing();
        $criteria = $this->normalizeCriteria($criteria);
        if (is_array($criteria) && count($criteria) > 0) {
            foreach ($criteria as $criterion) {
                $list->addConditionParam(
                    $criterion['condition'],
                    array_key_exists('variable', $criterion) ? $criterion['variable'] : null
                );
            }
        }
        if (is_array($orderBy) && count($orderBy) > 0) {
            $orderBy = $orderBy[0];

            if (null !== $orderBy) {
                $orderBy = $this->normalizeOrderBy($orderBy);

                if ($orderBy['key']) {
                    $list->setOrderKey($orderBy['key']);
                }

                $list->setOrder($orderBy['direction']);
            }
        }

        $list->setLimit($limit);
        $list->setOffset($offset);
        return $list->load();
    }

    /**
     * Checks whether the given managed entity exists in the database.
     *
     * @param object $entity
     * @return boolean TRUE if the entity exists in the database, FALSE otherwise.
     * @throws DBALException
     */
    public function exists($entity)
    {
        $sql = 'SELECT 1 FROM ' . $this->class->tableName;
        return (bool) $this->connection->fetchColumn($sql);
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getByPath(string $path)
    {
        $path = Service::correctPath($path);

        try {
            $object = $this->getListing()->getDao()->getByPath($path);

            return $this->loadById($object->getId());
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getFullQualifiedClassName()
    {
        return $this->getClassMetadata()->fullyQualifiedClassName($this->getClassMetadata()->name);
    }

    /**
     * @return mixed
     */
    protected function getListing()
    {
        if (!$this->listing) {
            $className = $this->getFullQualifiedClassName();
            $listClass = $className . '\\Listing';
            $this->listing = $this->modelFactory->build($listClass);
        }
        return $this->listing;
    }

    /**
     * Normalize critera input.
     *
     * Input could be
     *
     * [
     *     "condition" => "o_id=?",
     *     "conditionVariables" => [1]
     * ]
     *
     * OR
     *
     * [
     *     "condition" => [
     *          "o_id" => 1
     *     ]
     * ]
     *
     * @param array $criteria
     *
     * @return array
     */
    private function normalizeCriteria($criteria)
    {
        $normalized = [
        ];

        if (is_array($criteria)) {
            foreach ($criteria as $key => $criterion) {
                $normalizedCriterion = [];

                if (is_array($criterion)) {
                    if (array_key_exists('condition', $criterion)) {
                        if (is_string($criterion['condition'])) {
                            $normalizedCriterion['condition'] = $criterion['condition'];

                            if (array_key_exists('variable', $criterion)) {
                                $normalizedCriterion['variable'] = $criterion['variable'];
                            }
                        }
                    } else {
                        $normalizedCriterion['condition'] = $criterion;
                    }
                } else {
                    $normalizedCriterion['condition'] = $key . ' = ?';
                    $normalizedCriterion['variable'] = [$criterion];
                }

                if (count($normalizedCriterion) > 0) {
                    $normalized[] = $normalizedCriterion;
                }
            }
        }

        return $normalized;
    }

    /**
     * Normalizes Order By.
     *
     * [
     *      "key" => "o_id",
     *      "direction" => "ASC"
     * ]
     *
     * OR
     *
     * "o_id ASC"
     *
     * @param array|string $orderBy
     *
     * @return array
     */
    private function normalizeOrderBy($orderBy)
    {
        $normalized = [
            'key' => '',
            'direction' => 'ASC',
        ];

        if (is_array($orderBy)) {
            if (array_key_exists('key', $orderBy)) {
                $normalized['key'] = $orderBy['key'];
            }

            if (array_key_exists('direction', $orderBy)) {
                $normalized['direction'] = $orderBy['direction'];
            }
        } elseif (is_string($orderBy)) {
            $exploded = explode(' ', $orderBy);

            $normalized['key'] = $exploded[0];

            if (count($exploded) > 1) {
                $normalized['direction'] = $exploded[1];
            }
        }

        return $normalized;
    }
}
