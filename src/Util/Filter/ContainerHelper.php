<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use ArrayAccess\TrayDigita\Container\ContainerWrapper;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use Psr\Container\ContainerInterface;
use Throwable;
use function is_object;

/**
 * @template TObject of Object
 * @template ContainerFrozenException of \ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException
 * @template ContainerNotFoundException of \ArrayAccess\TrayDigita\Container\Exceptions\ContainerNotFoundException
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
class ContainerHelper
{
    /**
     * Get container or null
     *
     * @param class-string<TObject>|string $name
     * @param ?ContainerInterface $container
     * @return TObject|mixed|null
     */
    public static function getNull(
        string $name,
        ?ContainerInterface $container = null
    ) {
        try {
            $container ??= Decorator::container();
            return $container?->has($name) ? $container->get($name) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Expect the container
     *
     * @param string|class-string<TObject> $id
     * @param object|object<TObject>|class-string<TObject>|string $expect
     * @param ?ContainerInterface $container
     * @return TObject|mixed
     * @return bool|mixed|object|null
     */
    public static function expect(
        string $id,
        object|string $expect,
        ?ContainerInterface $container = null
    ): mixed {
        $container ??= Decorator::container();
        return $container ? ContainerWrapper::maybeContainerOrCreate(
            $container
        )->expect($id, $expect) : null;
    }

    /**
     * Use container expect
     *
     * @param object|object<TObject>|class-string<TObject>|string $expect
     * @param ?ContainerInterface $container
     * @return TObject|mixed
     * @return bool|mixed|object|null
     */
    public static function use(
        object|string $expect,
        ?ContainerInterface $container = null
    ): mixed {
        $id = is_object($expect) ? $expect::class : $expect;
        return self::expect($id, $expect, $container);
    }

    /**
     * Getting service
     *
     * @param string|class-string<TObject> $expect
     * @param ContainerInterface|null $container
     * @return mixed|TObject
     */
    public static function service(
        string $expect,
        ?ContainerInterface $container = null
    ) : mixed {
        return ContainerHelper::use(
            $expect,
            $container
        )??Decorator::service($expect);
    }

    /**
     * Get object instance or create if not available until interfaces
     * beware using this method, it will check an interface tree.
     *
     * @template O of object
     * @param class-string<O>|object $classString
     * @return O
     * @throws ContainerFrozenException
     * @throws ContainerNotFoundException
     * @throws Throwable
     */
    public static function decorate(
        string|object $classString,
        ?ContainerInterface $container = null
    ) {
        $container ??= Decorator::container();
        return $container ? ContainerWrapper::createFromContainer(
            $container
        )->decorate($classString) : null;
    }

    /**
     * Resolve the callable
     *
     * @param callable|array|class-string<TObject>|mixed $callable
     * @param ContainerInterface|null $container
     * @param array $arguments
     * @param ?array $fallback
     * @return array|TObject|mixed
     * @throws Throwable
     */
    public static function resolveCallable(
        mixed $callable,
        ?ContainerInterface $container = null,
        array $arguments = [],
        ?array $fallback = null
    ): mixed {
        $container ??= Decorator::container();
        return ContainerWrapper::maybeContainerOrCreate($container)
            ->getResolver()->resolveCallable($callable, $arguments, $fallback);
    }
}
