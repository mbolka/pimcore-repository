<?php
/**
 * @category    pimcore-repository
 * @date        06/06/2019
 * @author      Michał Bolka <michal.bolka@gmail.com>
 */
namespace Bolka\RepositoryBundle\Common\Collections;

use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\ExpressionBuilder;

/**
 * Class Criteria
 * @method Criteria where(Expression $expression)
 */
class Criteria extends \Doctrine\Common\Collections\Criteria
{

    /** @var ExpressionBuilder|null */
    private static $expressionBuilder;

    /**
     * @var bool
     */
    protected $hideUnpublished = true;

    /**
     * @return bool
     */
    public function isHideUnpublished(): bool
    {
        return $this->hideUnpublished;
    }

    /**
     * @param bool $hideUnpublished
     * @return Criteria
     */
    public function setHideUnpublished(bool $hideUnpublished)
    {
        $this->hideUnpublished = $hideUnpublished;
        return $this;
    }

    /**
     * Creates an instance of the class.
     *
     * @return Criteria
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Returns the expression builder.
     *
     * @return ExpressionBuilder
     */
    public static function expr()
    {
        if (self::$expressionBuilder === null) {
            self::$expressionBuilder = new ExpressionBuilder();
        }

        return self::$expressionBuilder;
    }
}