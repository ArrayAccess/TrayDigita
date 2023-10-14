<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Traits;

use ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException;
use ArrayAccess\TrayDigita\Container\Exceptions\ContainerNotFoundException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ReflectionClass;
use ReflectionObject;
use Throwable;
use function class_exists;
use function gettype;
use function is_array;
use function is_callable;
use function is_float;
use function is_int;
use function is_iterable;
use function is_null;
use function is_numeric;
use function is_object;
use function is_resource;
use function is_string;
use function sprintf;
use function strtolower;

trait ContainerDecorator
{
    /**
     * @template O of object
     * @param string $id
     * @param callable|class-string<O>|mixed $container
     * @param ...$arguments
     * @throws ContainerFrozenException
     */
    abstract public function set(string $id, mixed $container, ...$arguments);

    /**
     * @template O of object
     * @param string|class-string<O> $id
     * @return O|mixed
     * @throws ContainerFrozenException
     * @throws ContainerNotFoundException
     * @throws Throwable
     */
    abstract public function get(string $id);

    abstract public function has(string $id) : bool;

    private function assertAnonymous(object $object): void
    {
        if (str_contains($object::class, '@anonymous')) {
            throw new UnsupportedArgumentException(
                'Container does not support decorating anonymous instance.'
            );
        }
    }

    /**
     * @template T of object
     * @param string|class-string<T> $id
     * @param object|object<T>|class-string<T>|string $expect
     * @return T|mixed
     * @return bool|mixed|object|null
     */
    public function expect(string $id, object|string $expect): mixed
    {
        if (!$this->has($id)) {
            return null;
        }
        try {
            $container = $this->get($id);
            $typeString = is_string($expect)
                ? strtolower(trim($expect))
                : null;
            $isType = $typeString && match ($typeString) {
                'double', 'float' => is_float($container),
                'numeric', 'number' => is_numeric($container),
                'integer', 'int' => is_int($container),
                'array' => is_array($container),
                'iterable' => is_iterable($container),
                'object' => is_object($container),
                'null' => is_null($container),
                'resource' => is_resource($container),
                'callable' => is_callable($container),
                'string' => is_string($container),
                default => gettype($container) === strtolower($expect)
            };
            if ($isType) {
                return $container;
            }
            if (!is_object($container)) {
                return null;
            }
            if (is_object($expect)) {
                return $container === $expect ? $container : null;
            }
            if (!$typeString) {
                return null;
            }
            return strtolower($container::class) === $typeString || is_a($container, $expect)
                ? $container
                : null;
        } catch (Throwable) {
        }
        return null;
    }

    /**
     * Get object instance or create if not available until interfaces
     * beware using this method, it will check interface tree.
     *
     * @template O of object
     * @param class-string<O>|object $classString
     * @return O
     * @throws ContainerFrozenException
     * @throws ContainerNotFoundException
     * @throws Throwable
     */
    public function decorate(string|object $classString)
    {
        if (is_object($classString)) {
            $this->assertAnonymous($classString);
            $classRef = new ReflectionObject($classString);
            foreach ($classRef->getInterfaces() as $interface) {
                $interfaceName = $interface->getName();
                if ($this->has($interfaceName)) {
                    return $this->get($interfaceName);
                }
            }
            $className = $classString::class;
            if (!$this->has($className)) {
                $this->set($className, fn () => $classString);
            }
            return $this->get($className);
        }

        if ($this->has($classString)) {
            return $this->get($classString);
        }

        if (!class_exists($classString)) {
            throw new UnsupportedArgumentException(
                sprintf('Argument must be as class name. class "%s" is not exists.', $classString)
            );
        }

        $classRef = new ReflectionClass($classString);
        $classString = $classRef->getName();
        foreach ($classRef->getInterfaces() as $interface) {
            $interfaceName = $interface->getName();
            if ($this->has($interfaceName)) {
                return $this->get($interfaceName);
            }
        }

        if (!$classRef->isInstantiable()) {
            throw new UnsupportedArgumentException(
                sprintf('Class "%s" is not instantiable.', $classString)
            );
        }

        if (!$this->has($classString)) {
            $this->set($classString, $classString);
        }

        return $this->get($classString);
    }
}
