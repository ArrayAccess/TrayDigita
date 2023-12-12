<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Helper;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Expression as DoctrineExpression;
use Doctrine\Common\Collections\Expr\CompositeExpression;

/**
 * Expression helper class
 *
 * @mixin \Doctrine\Common\Collections\ExpressionBuilder
 * @method static \Doctrine\Common\Collections\Expr\Comparison eq(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison gt(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison lt(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison gte(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison neq(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison isNull(string $field)
 * @method static \Doctrine\Common\Collections\Expr\Comparison in(string $field, array $values)
 * @method static \Doctrine\Common\Collections\Expr\Comparison notIn(string $field, array $values)
 * @method static \Doctrine\Common\Collections\Expr\Comparison contains(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison memberOf(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison startsWith(string $field, mixed $value)
 * @method static \Doctrine\Common\Collections\Expr\Comparison endsWith(string $field, mixed $value)
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @see \Doctrine\Common\Collections\ExpressionBuilder
 */
class Expression
{
    /**
     * Expression andX
     *
     * @param DoctrineExpression ...$expressions
     * @return CompositeExpression
     * @see \Doctrine\Common\Collections\ExpressionBuilder::andX()
     */
    public static function andX(DoctrineExpression ...$expressions): CompositeExpression
    {
        return Criteria::expr()->andX(...$expressions);
    }

    /**
     * Expression orX
     *
     * @param DoctrineExpression ...$expressions
     * @return CompositeExpression
     * @see \Doctrine\Common\Collections\ExpressionBuilder::orX()
     */
    public static function orX(DoctrineExpression ...$expressions): CompositeExpression
    {
        return Criteria::expr()->orX(...$expressions);
    }

    /**
     * Expression not
     *
     * @param DoctrineExpression $expression
     * @return CompositeExpression
     * @see \Doctrine\Common\Collections\ExpressionBuilder::not()
     */
    public static function not(DoctrineExpression $expression): CompositeExpression
    {
        return Criteria::expr()->not($expression);
    }

    /**
     * Magic method to call the expression builder methods
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return Criteria::expr()->$name(...$arguments);
    }

    /**
     * Magic method to call the expression builder methods
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return Criteria::expr()->$name(...$arguments);
    }

    /**
     * Create a new Criteria
     *
     * @return Criteria
     */
    public static function criteria(): Criteria
    {
        return Criteria::create();
    }
}
