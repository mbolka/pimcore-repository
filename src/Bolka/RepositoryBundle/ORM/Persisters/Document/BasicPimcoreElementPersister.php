<?php
/**
 * @category    pimcore-repository
 * @date        11/05/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */

namespace Bolka\RepositoryBundle\ORM\Persisters\Document;

use Bolka\RepositoryBundle\Common\Collections\Criteria;
use Bolka\RepositoryBundle\ORM\Mapping\ElementMetadataInterface;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\Persisters\SqlValueVisitor;
use Exception;
use Pimcore\Db\Connection;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Listing;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\AbstractElement;
use Pimcore\Model\FactoryInterface;
use Bolka\RepositoryBundle\ORM\Persisters\SqlExpressionVisitor;
use Bolka\RepositoryBundle\ORM\PimcoreElementManagerInterface;

/**
 * Class BasicPimcoreAbstractElementPersister
 * @package Bolka\RepositoryBundle\ORM\Persisters\Entity
 */
class BasicPimcoreElementPersister implements PimcoreAbstractElementPersisterInterface
{

    /**
     * @var array
     */
    protected static $comparisonMap = [
        Comparison::EQ          => '= %s',
        Comparison::NEQ         => '!= %s',
        Comparison::GT          => '> %s',
        Comparison::GTE         => '>= %s',
        Comparison::LT          => '< %s',
        Comparison::LTE         => '<= %s',
        Comparison::IN          => 'IN (%s)',
        Comparison::NIN         => 'NOT IN (%s)',
        Comparison::CONTAINS    => 'LIKE %s',
        Comparison::STARTS_WITH => 'LIKE %s',
        Comparison::ENDS_WITH   => 'LIKE %s',
    ];

    /**
     * @var PimcoreElementManagerInterface
     */
    private $em;
    /**
     * @var ElementMetadataInterface
     */
    private $class;

    /**
     * Queued inserts.
     *
     * @var AbstractElement[]
     */
    protected $queuedInserts = [];
    /**
     * @var FactoryInterface
     */
    private $modelFactory;

    /** @var Connection */
    private $connection;

    /**
     * BasicPimcoreAbstractElementPersister constructor.
     * @param PimcoreElementManagerInterface $em
     * @param ElementMetadataInterface       $class
     * @param FactoryInterface               $modelFactory
     */
    public function __construct(
        PimcoreElementManagerInterface $em,
        ElementMetadataInterface $class,
        FactoryInterface $modelFactory
    ) {
        $this->em    = $em;
        $this->class = $class;
        $this->modelFactory = $modelFactory;
        $this->connection = $em->getConnection();
    }

    /**
     * @return ElementMetadataInterface
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
     * @param AbstractElement $element
     * @return void
     */
    public function addInsert(AbstractElement $element)
    {
        $this->queuedInserts[spl_object_hash($element)] = $element;
    }

    /**
     * Executes all queued entity insertions and returns any generated post-insert
     * identifiers that were created as a result of the insertions.
     *
     * If no inserts are queued, invoking this method is a NOOP.
     *
     * @return array An array of any generated post-insert IDs. This will be an empty array
     *               if the entity class does not use the IDENTITY generation strategy.
     * @throws Exception
     */
    public function executeInserts()
    {
        $insertedIds = [];
        foreach ($this->queuedInserts as $insert) {
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
     * @param AbstractElement $AbstractElement The AbstractElement to update.
     *
     * @return void
     * @throws Exception
     */
    public function update(AbstractElement $AbstractElement)
    {
        $AbstractElement->save();
    }

    /**
     * Deletes a managed entity.
     *
     * The entity to delete must be managed and have a persistent identifier.
     * The deletion happens instantaneously.
     *
     * Subclasses may override this method to customize the semantics of entity deletion.
     *
     * @param AbstractElement $AbstractElement The entity to delete.
     *
     * @return void TRUE if the entity got deleted in the database, FALSE otherwise.
     * @throws Exception
     */
    public function delete(AbstractElement $AbstractElement)
    {
        $AbstractElement->delete();
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
        /** @var Listing $list */
        $list = $this->modelFactory->build($listClass);
        $list->setValues($criteria);
        $list->setUnpublished($criteria instanceof Criteria ? !$criteria->isHideUnpublished(): false);
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
     */
    public function loadById(array $identifier, $entity = null)
    {
        return $this->load($identifier, $entity);
    }

    /**
     * Refreshes a managed entity.
     *
     * @param AbstractElement $entity The entity to refresh.
     * @return void
     * @throws Exception
     */
    public function refresh(AbstractElement $entity)
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
                    array_key_exists('variable', $criterion) ? $criterion['variable'] : null,
                    $criterion['concatenator']
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
     * @param AbstractElement $entity
     * @return bool
     * @throws DBALException
     */
    public function exists(AbstractElement $entity)
    {
        $criteria = $this->class->getIdentifierValues($entity);
        $params = [];
        if (!$criteria) {
            return false;
        }
        $params[] = reset($criteria);
        $key = key($criteria);
        $table = $this->class->getTableName();
        $sql = sprintf("SELECT 1 FROM %s WHERE '%s' =?", $table, $key);

        return (bool) $this->connection->fetchColumn($sql, $params, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function expandCriteriaParameters(Criteria $criteria)
    {
        $expression = $criteria->getWhereExpression();
        $sqlParams  = [];
        $sqlTypes   = [];

        if ($expression === null) {
            return [$sqlParams, $sqlTypes];
        }

        $valueVisitor = new SqlValueVisitor();

        $valueVisitor->dispatch($expression);

        list($params) = $valueVisitor->getParamsAndTypes();

        foreach ($params as $param) {
            $sqlParams = array_merge($sqlParams, $this->getValues($param));
        }

        return [$sqlParams, []];
    }

    /**
     * Retrieves the parameters that identifies a value.
     *
     * @param mixed $value
     *
     * @return array
     */
    private function getValues($value)
    {
        if (is_array($value)) {
            $newValue = [];

            foreach ($value as $itemValue) {
                $newValue = array_merge($newValue, $this->getValues($itemValue));
            }

            return [$newValue];
        }
        return [$value];
    }

    /**
     * Gets the Select Where Condition from a Criteria object.
     *
     * @param Criteria $criteria
     *
     * @return string
     */
    protected function getSelectConditionCriteriaSQL(Criteria $criteria)
    {
        $expression = $criteria->getWhereExpression();

        if ($expression === null) {
            return '';
        }

        $visitor = new SqlExpressionVisitor($this, $this->class);

        return $visitor->dispatch($expression);
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getByPath(string $path)
    {
        $path = Service::correctPath($path);

        try {
            /** @var Concrete $object */
            $object = $this->getListing()->getDao()->getByPath($path);

            return $this->loadById([$object->getId()]);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return string|null
     */
    public function getFullQualifiedClassName()
    {
        return $this->getClassMetadata()->fullyQualifiedClassName($this->getClassMetadata()->name);
    }

    /**
     * @return mixed
     */
    protected function getListing()
    {
        $className = $this->class->getName();
        $listClass = $className . '\\Listing';
        return $this->modelFactory->build($listClass);
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
                            if (!strpos($criterion['condition'], '?')) {
                                $normalizedCriterion['condition'] =  '`' . $criterion['condition'] . '` = ?';
                            } else {
                                $normalizedCriterion['condition'] = $criterion['condition'];
                            }
                            if (array_key_exists('variable', $criterion)) {
                                $normalizedCriterion['variable'] = $criterion['variable'];
                            }
                        }
                    } else {
                        if (!strpos($criterion['condition'], '?')) {
                            $normalizedCriterion['condition'] =  '`' . $criterion['condition'] . '` = ?';
                        } else {
                            $normalizedCriterion['condition'] = $criterion;
                        }
                    }
                    if (array_key_exists('concatenator', $criterion)) {
                        $normalizedCriterion['concatenator'] = $criterion['concatenator'];
                    } else {
                        $normalizedCriterion['concatenator'] = 'AND';
                    }
                } else {
                    $normalizedCriterion['condition'] =  '`' . $key . '` = ?';
                    $normalizedCriterion['variable'] = $criterion;
                    $normalizedCriterion['concatenator'] = 'AND';
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

    /**
     * {@inheritdoc}
     */
    public function loadCriteria(Criteria $criteria)
    {
        $orderBy = $criteria->getOrderings();
        $limit   = $criteria->getMaxResults();
        $offset  = $criteria->getFirstResult();
        $hideUnpublished = $criteria->isHideUnpublished();
        $query = $this->getSelectConditionCriteriaSQL($criteria);
        list($criteriaParams) = $this->expandCriteriaParameters($criteria);
        /** @var Listing $objectListing */
        $objectListing = $this->getListing();
        $objectListing
            ->setCondition($query, $criteriaParams)
            ->setLimit($limit)
            ->setOffset($offset)
            ->setOrder($orderBy)
            ->setUnpublished(!$hideUnpublished);
        return $objectListing->load();
    }
}
