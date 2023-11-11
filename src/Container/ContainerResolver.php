<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container;

use ArrayAccess\TrayDigita\Container\Exceptions\ContainerNotFoundException;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\SystemContainerInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\UnInvokableInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\Logical\UnResolveAbleException;
use ArrayAccess\TrayDigita\Http\Factory\RequestFactory;
use ArrayAccess\TrayDigita\Http\Factory\ResponseFactory;
use ArrayAccess\TrayDigita\Http\Request;
use ArrayAccess\TrayDigita\Http\Response;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use function array_key_exists;
use function array_unshift;
use function class_exists;
use function count;
use function end;
use function gettype;
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
    private bool $hasAliasedMethod;
    private bool $hasParameterMethod;

    public function __construct(protected ContainerInterface $container)
    {
        $isContainer = $this->container instanceof ContainerInterface;
        $this->hasAliasedMethod = $isContainer|| method_exists($this->container, 'getAliases');
        $this->hasParameterMethod = $isContainer || method_exists($this->container, 'getParameters');
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @template T of object
     * @param callable|array|class-string<T>|mixed $callable
     * @param array $arguments
     * @param array|null $fallback
     * @return array|T|mixed
     * @throws Throwable
     */
    public function resolveCallable(
        mixed $callable,
        array $arguments = [],
        ?array $fallback = null
    ): mixed {
        $container = $this->getContainer();
        $value = $callable;
        if (is_string($callable) && class_exists($callable)) {
            $ref = new ReflectionClass($callable);
            if ($ref->isInstantiable()) {
                $arguments = $this->resolveArguments($ref, $arguments, $fallback);
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

            if (is_callable($callable)) {
                $arguments = $this
                    ->resolveArguments(
                        (is_string($callable) || $callable instanceof Closure)
                            ? new ReflectionFunction($callable)
                            : new ReflectionMethod(...$callable),
                        $arguments,
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
        if (!$container instanceof SystemContainerInterface || !$container->hasQueuedService($id)) {
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
     * Resolve argument for builtin & object
     *
     * @param ReflectionNamedType $type
     * @param mixed $argumentValue
     * @param $found
     * @return mixed
     */
    private function resolveTheArgumentObjectBuiltin(
        ReflectionNamedType $type,
        mixed $argumentValue,
        &$found = null
    ) : mixed {
        $found = false;
        if ($argumentValue === null && $type->allowsNull()) {
            $found = true;
            return null;
        }
        if ($type->isBuiltin()) {
            $builtin = [
                'bool' => 'boolean',
                'float' => 'double',
            ];
            $argType = gettype($argumentValue);
            $argType = $builtin[$argType]??$argType;
            $argName = $builtin[$type->getName()]??$type->getName();
            $found = $argType === $argName;
            return $found ? $argumentValue : null;
        }
        if (is_object($argumentValue) && is_a($argumentValue, $type->getName())) {
            $found = true;
            return $argumentValue;
        }
        return null;
    }

    private function resolveFactoryObject(ReflectionNamedType $type, &$found = null)
    {
        $found = false;
        if ($type->isBuiltin()) {
            return null;
        }
        $name = $type->getName();
        $factory = [
            ServerRequest::class => ServerRequestInterface::class,
            Request::class => RequestInterface::class,
            Response::class => ResponseInterface::class,
            ServerRequestInterface::class => ServerRequestInterface::class,
            RequestInterface::class => RequestInterface::class,
            ResponseInterface::class => ResponseInterface::class,
        ];
        if (!isset($factory[$name])) {
            return null;
        }
        $container = $this->getContainer();
        $name = $factory[$name];
        switch ($name) {
            case RequestInterface::class:
            case ServerRequestInterface::class:
                $found = true;
                $serverRequest = ServerRequest::fromGlobals(
                    ContainerHelper::use(ServerRequestFactoryInterface::class, $container),
                    ContainerHelper::use(StreamFactoryInterface::class, $container)
                );
                if ($name === ServerRequestInterface::class) {
                    return $serverRequest;
                }
                return (ContainerHelper::getNull(
                    RequestFactoryInterface::class,
                    $this->getContainer()
                )??new RequestFactory())->createRequest($serverRequest->getMethod(), $serverRequest->getUri());
            case ResponseInterface::class:
                $found = true;
                return (ContainerHelper::getNull(
                    ResponseFactoryInterface::class,
                    $this->getContainer()
                )??new ResponseFactory())->createResponse();
        }
        return null;
    }

    private function resolveInternalArgument(
        ReflectionParameter $parameter,
        ReflectionType $refType,
        int $offset,
        array $arguments,
        array $containerParameters,
        array $containerAliases,
        array &$paramArguments,
        &$paramFound = null
    ): void {
        $paramFound = false;
        if ($refType instanceof ReflectionUnionType) {
            foreach ($refType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType) {
                    $this->resolveInternalArgument(
                        $parameter,
                        $type,
                        $offset,
                        $arguments,
                        $containerParameters,
                        $containerAliases,
                        $paramArguments,
                        $paramFound
                    );
                    if ($paramFound) {
                        return;
                    }
                }
            }
            return;
        }
        if (!$refType instanceof ReflectionNamedType) {
            return;
        }

        $parameterName = $parameter->getName();
        $refName = $refType->getName();
        if ($arguments !== []) {
            foreach ([$refName, $parameterName, $offset] as $val) {
                if (!array_key_exists($val, $arguments)) {
                    continue;
                }
                $value = $this->resolveTheArgumentObjectBuiltin($refType, $arguments[$val], $paramFound);
                if ($paramFound) {
                    $paramArguments[$parameter->getName()] = $value;
                    return;
                }
            }
        }

        if (array_key_exists($parameterName, $containerParameters)) {
            $value = $this->resolveTheArgumentObjectBuiltin(
                $refType,
                $containerParameters[$parameterName],
                $paramFound
            );
            if ($paramFound) {
                $paramArguments[$parameterName] = $value;
                return;
            }
        }

        if ($refName === ContainerInterface::class
            || $refName === SystemContainerInterface::class
            || is_a($refName, $this->container::class)) {
            $paramFound = true;
            if ($this->container->has($refName)) {
                try {
                    $param = $this->container->get($refName);
                    if (is_object($param) && is_a($param, ContainerInterface::class)) {
                        $paramArguments[$parameterName] = $param;
                        return;
                    }
                } catch (Throwable) {
                }
            }
            $paramArguments[$parameterName] = $this->container;
            return;
        }

        if (!$refType->isBuiltin()) {
            if ($this->container->has($refName)) {
                try {
                    $value = $this->resolveTheArgumentObjectBuiltin(
                        $refType,
                        $this->container->get($refName),
                        $paramFound
                    );
                    if ($paramFound) {
                        $paramArguments[$parameterName] = $value;
                        return;
                    }
                } catch (Throwable) {
                }
            }
            if (isset($containerAliases[$parameterName])
                && $this->container->has($containerAliases[$parameterName])
            ) {
                try {
                    $value = $this->resolveTheArgumentObjectBuiltin(
                        $refType,
                        $this->container->get($containerAliases[$parameterName]),
                        $paramFound
                    );
                    if ($paramFound) {
                        $paramArguments[$parameterName] = $value;
                        return;
                    }
                } catch (Throwable) {
                }
            }
        }
        if (($isDefault = $parameter->isDefaultValueAvailable())
            || $parameter->allowsNull()
        ) {
            $paramFound = true;
            try {
                $paramArguments[$parameterName] = $isDefault ? $parameter->getDefaultValue() : null;
                return;
            } catch (ReflectionException) {
            }
        }
        if ($paramFound) {
            return;
        }
        $res = $this->resolveFactoryObject($refType, $paramFound);
        if ($paramFound) {
            $paramArguments[$parameterName] = $res;
        }
    }

    /**
     * @throws Throwable
     */
    public function resolveArguments(
        ReflectionClass|ReflectionFunctionAbstract $reflection,
        $arguments,
        ?array $fallback = null
    ): array {
        $reflectionName = $reflection->getName();
        $reflection = $reflection instanceof ReflectionClass
            ? $reflection->getConstructor()
            : $reflection;

        // resolver empty arguments when auto resolve enabled
        /*if (!empty($arguments) && count($arguments) === $reflection->getNumberOfRequiredParameters()) {
            return $arguments;
        }*/
        $parameters = $reflection?->getParameters()??[];
        $container = $this->getContainer();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $containerParameters = (array) ($this->hasParameterMethod ? $container->getParameters() : []);
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $containerAliases = (array) ($this->hasAliasedMethod ? $container->getAliases() : []);
        $paramArguments = [];
        foreach ($parameters as $offset => $parameter) {
            $type = $parameter->getType();
            $this->resolveInternalArgument(
                $parameter,
                $type,
                $offset,
                $arguments,
                $containerParameters,
                $containerAliases,
                $paramArguments,
                $found
            );
            if ($found) {
                continue;
            }
            // go to default fallback
            if ($fallback && count($fallback) > $reflection->getNumberOfRequiredParameters()) {
                return $fallback;
            }
            $paramArguments = [];
            break;
        }
        if (($required = $reflection?->getNumberOfRequiredParameters()??0) > count($paramArguments)) {
            throw new UnResolveAbleException(
                sprintf(
                    'Could not resolve required arguments for : %s. Required %d argument, but %d given',
                    $reflectionName,
                    $required,
                    count($paramArguments)
                )
            );
        }
        return $paramArguments;
    }
}
