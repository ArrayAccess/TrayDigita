<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing;

use Closure;
use ReflectionException;
use ReflectionFunction;

final class CallableAbstractController extends AbstractController
{
    /**
     * @var callable
     */
    private $callback;

    protected function route(...$arguments)
    {
        $callback = $this->callback;
        unset($this->callback);
        try {
            if ($callback instanceof Closure) {
                $ref = new ReflectionFunction($callback);
                if (!$ref->isStatic() && !$ref->getClosureThis()) {
                    $callback = $callback->bindTo($this, $this);
                }
            }
        } catch (ReflectionException) {
        }

        return $callback(...$arguments);
    }

    public static function attach(Router $router, callable $callback): CallableAbstractController
    {
        $router = new self($router);
        $router->callback = $callback;
        return $router;
    }
}
