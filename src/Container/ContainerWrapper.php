<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container;

use ArrayAccess;
use ArrayAccess\TrayDigita\Container\Interfaces\UnInvokableInterface;
use ArrayAccess\TrayDigita\Container\Traits\ContainerDecorator;
use ArrayAccess\TrayDigita\Exceptions\Logical\InvokeAbleException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;
use function method_exists;
use function sprintf;

class ContainerWrapper implements ContainerInterface, ArrayAccess, UnInvokableInterface
{
    private ContainerInterface $container;

    use ContainerDecorator;

    private ?ContainerResolver $resolver;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        if ($container instanceof Container) {
            $this->resolver = $container->getResolver();
        } else {
            $this->resolver = new ContainerResolver($container);
        }
    }

    public static function createFromContainer(ContainerInterface $container) : static
    {
        return new static($container);
    }

    public static function maybeContainerOrCreate(ContainerInterface $container) : static|ContainerWrapper|Container
    {
        if ($container instanceof ContainerWrapper || $container instanceof Container) {
            return $container;
        }

        return static::createFromContainer($container);
    }

    public function get(string $id)
    {
        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function remove(string $id): void
    {
        if ($this->container instanceof Container) {
            $this->container->remove($id);
            return;
        }
        if (method_exists($this->container, 'remove')) {
            $this->container->remove($id);
            return;
        }
        if ($this->container instanceof ArrayAccess) {
            unset($this->container[$id]);
        }
    }

    public function set(string $id, mixed $container, ...$arguments): void
    {
        if ($this->container instanceof Container) {
            $this->container->set($id, $container, ...$arguments);
            return;
        }
        if (method_exists($this->container, 'set')) {
            $this->container->set($id, $container, ...$arguments);
            return;
        }
        if ($this->container instanceof ArrayAccess) {
            $this->container[$id] = $container;
        }
    }

    public function getResolver(): ?ContainerResolver
    {
        return $this->resolver;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    public function __invoke()
    {
        throw new InvokeAbleException(
            sprintf('Class %s is not invokable', $this::class)
        );
    }
}
