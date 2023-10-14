<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Helper;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;

/**
 * @mixin ExpressionBuilder
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
