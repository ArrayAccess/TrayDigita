<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Helper;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\ExpressionBuilder;
use Doctrine\Common\Collections\Expr\Expression as DoctrineExpression;

/**
 * @mixin ExpressionBuilder
 * @method static CompositeExpression andX(DoctrineExpression ...$expressions)
 * @method static CompositeExpression orX(DoctrineExpression ...$expressions)
 * @method static CompositeExpression not(DoctrineExpression $expression)
 * @method static Comparison eq(string $field, mixed $value)
 * @method static Comparison gt(string $field, mixed $value)
 * @method static Comparison lt(string $field, mixed $value)
 * @method static Comparison gte(string $field, mixed $value)
 * @method static Comparison neq(string $field, mixed $value)
 * @method static Comparison isNull(string $field)
 * @method static Comparison in(string $field, array $values)
 * @method static Comparison notIn(string $field, array $values)
 * @method static Comparison contains(string $field, mixed $value)
 * @method static Comparison memberOf(string $field, mixed $value)
 * @method static Comparison startsWith(string $field, mixed $value)
 * @method static Comparison endsWith(string $field, mixed $value)
 */
class Expression
{
    public function __call(string $name, array $arguments)
    {
        return Criteria::expr()->$name(...$arguments);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return Criteria::expr()->$name(...$arguments);
    }

    public static function criteria(): Criteria
    {
        return Criteria::create();
    }
}
