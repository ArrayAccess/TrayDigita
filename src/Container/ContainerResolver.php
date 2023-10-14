<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container;

use ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException;
use ArrayAccess\TrayDigita\Container\Exceptions\ContainerNotFoundException;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\UnInvokableInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\Logical\UnResolveAbleException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;
use function array_key_exists;
use function array_unshift;
use function class_exists;
use function count;
use function end;
use function is_a;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;
use function reset;
use function sprintf;

class ContainerResolver implements ContainerIndicateInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @param callable|array|mixed $callable
     * @param array $arguments
     * @return array|mixed
     * @throws ContainerFrozenException
     * @throws ContainerNotFoundException
     * @throws Throwable
     */
    public function resolveCallable(mixed $callable, array $arguments = []): mixed
    {
        $container = $this->getContainer();
        $value = $callable;
        if (is_string($callable)
            && Consolidation::isValidClassName($callable)
            && class_exists($callable)
        ) {
            $ref = new ReflectionClass($callable);
            if ($ref->isInstantiable()) {
                $arguments = $this->resolveArguments($ref, $arguments);
                // resolver empty arguments when auto resolve enabled
                $value = new $callable(
                    ...$arguments
                );
            }
        } elseif (!$callable instanceof UnInvokableInterface) {
            if ($callable instanceof ContainerInvokable
                && !reset($arguments) instanceof ContainerInterface
            ) {
                array_unshift($arguments, $this);
            }
            if (is_array($callable)
                && count($callable) === 2
                && is_string(reset($callable))
            ) {
                $first = reset($callable);
                if ($container->has($first)) {
                    $callable = [$container->get($first), end($callable)];
                } elseif (method_exists($container, 'hasAlias')
                    && method_exists($container, 'getAlias')
                    && $container->hasAlias($first)
                    && ($alias = $container->getAlias($first))
                    && is_string($alias)
                ) {
                    $callable = [$container->get($alias), end($callable)];
                }
            }

            if (empty($arguments) && is_callable($callable)) {
                $arguments = $this
                    ->resolveArguments(
                        (is_string($callable) || $callable instanceof Closure)
                            ? new ReflectionFunction($callable)
                            : new ReflectionMethod(...$callable),
                        $arguments
                    );
            }

            $value = is_callable($callable) ? $callable(...$arguments) : $callable;
        }
        $this->allocateService($value);
        return $value;
    }

    public function allocateService($containerValue) : void
    {
        if (!is_object($containerValue)) {
            return;
        }

        $container = $this->getContainer();
        // add container if container empty
        try {
            if ($containerValue instanceof ContainerAllocatorInterface
                && ! $containerValue->getContainer()
            ) {
                $containerValue->setContainer($container);
            }
        } catch (Throwable) {
        }
        try {
            if (!$containerValue instanceof ManagerAllocatorInterface
                || $containerValue->getManager()
            ) {
                return;
            }
            $manager = $container->has(ManagerInterface::class)
                ? $container->get(ManagerInterface::class)
                : null;
            if (!$manager instanceof ManagerInterface) {
                return;
            }
            $containerValue->setManager($manager);
        } catch (Throwable) {
        }
    }

    /**
     * @param string $id
     * @return array|mixed
     * @throws ContainerNotFoundException
     * @throws Throwable
     */
    public function resolve(string $id): mixed
    {
        $container = $this->getContainer();
        if (!$container instanceof Container || !$container->hasQueuedService($id)) {
            throw new ContainerNotFoundException(
                sprintf('Queued container service %s has not found.', $id)
            );
        }

        $callable = $container->getQueueService($id);
        $arguments = $container->hasArgument($id)
            ? $container->getArgument($id)
            : [];
        return $this->resolveCallable($callable, $arguments);
    }

    /**
     * @throws Throwable
     * @throws ContainerNotFoundException
     * @throws ContainerFrozenException
     * @throws UnResolveAbleException
     */
    public function resolveArguments(
        ReflectionClass|ReflectionFunctionAbstract $reflection,
        $arguments
    ): array {
        // resolver empty arguments when auto resolve enabled
        if (!empty($arguments)) {
            return $arguments;
        }
        $reflectionName = $reflection->getName();
        $reflection = $reflection instanceof ReflectionClass
            ? $reflection->getConstructor()
            : $reflection;
        $parameters = $reflection?->getParameters()??[];
        $container = $this->getContainer();
        $containerParameters = method_exists($container, 'getParameters')
            ? $container->getParameters()
            : [];
        $containerParameters = (array) $containerParameters;
        $containerAliases = method_exists($container, 'getAliases')
            ? $container->getAliases()
            : [];
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionUnionType) {
                foreach ($type->getTypes() as $unionType) {
                    if (!$unionType instanceof ReflectionNamedType
                        || $unionType->isBuiltin()
                    ) {
                        continue;
                    }

                    $name = $unionType->getName();
                    if ($name === ContainerInterface::class
                        || is_a($name, __CLASS__)
                        || $container->has($name)
                    ) {
                        $type = $unionType;
                        break;
                    }
                }
            }
            if (!$type instanceof ReflectionNamedType
                || $type->isBuiltin()
            ) {
                if (array_key_exists($parameter->getName(), $containerParameters)) {
                    $arguments[] = $containerParameters[$parameter->getName()];
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }
                if ($parameter->allowsNull()) {
                    $arguments[] = null;
                    continue;
                }
                $arguments = [];
                break;
            }

            $name = $type->getName();
            if ($name === ContainerInterface::class
                || is_a($name, __CLASS__)
            ) {
                $arguments[] = $container->has($name)
                    ? $container->get($name)
                    : $container;
                continue;
            }
            if (!$container->has($name)
                && isset($containerAliases[$name])
                && $container->has($containerAliases[$name])
            ) {
                $arguments[] = $container->get($containerAliases[$name]);
                continue;
            }

            if (!$container->has($name)) {
                if (array_key_exists($name, $containerParameters)) {
                    $param = $containerParameters[$name];
                    if (is_string($param) && $container->has($param)) {
                        $param = $container->get($param);
                    }
                    $arguments[] = $param;
                    continue;
                }
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }
                if ($parameter->allowsNull()) {
                    $arguments[] = null;
                    continue;
                }
                $arguments = [];
                break;
            }
            $arguments[] = $container->get($name);
        }
        if (($required = $reflection?->getNumberOfRequiredParameters()??0) > count($arguments)) {
            throw new UnResolveAbleException(
                sprintf(
                    'Could not resolve required arguments for : %s. Required %d argument, but %d given',
                    $reflectionName,
                    $required,
                    count($arguments)
                )
            );
        }
        return $arguments;
    }
}
