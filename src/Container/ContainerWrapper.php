<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container;

use ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException;
use ArrayAccess\TrayDigita\Container\Exceptions\ContainerNotFoundException;
use ArrayAccess\TrayDigita\Container\Interfaces\SystemContainerInterface;
use ArrayAccess\TrayDigita\Container\Traits\ContainerDecorator;
use ArrayAccess\TrayDigita\Exceptions\Logical\InvokeAbleException;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SensitiveParameter;
use Throwable;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function method_exists;
use function sprintf;

final class ContainerWrapper implements SystemContainerInterface
{
    use ContainerDecorator,
        CallStackTraceTrait;

    private ContainerInterface|SystemContainerInterface $container;

    private ?ContainerResolver $resolver = null;

    /**
     * @var array
     */
    private array $aliases = [];

    private array $queuedServices = [];

    private array $arguments = [];

    private array $parameters = [];

    private array $rawServices = [];

    private array $frozenServices = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        if (!$container instanceof SystemContainerInterface) {
            $this->resolver = new ContainerResolver($this);
        }
    }

    /**
     * @param string $id
     * @return void
     * @throws ContainerFrozenException
     */
    private function assertFrozen(string $id): void
    {
        if ($this->isFrozen($id)) {
            throw new ContainerFrozenException(
                sprintf('Container %s has frozen', $id)
            );
        }
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public static function createFromContainer(ContainerInterface $container) : ContainerWrapper
    {
        return new ContainerWrapper($container);
    }

    public static function maybeContainerOrCreate(
        ContainerInterface $container
    ) : SystemContainerInterface {
        if ($container instanceof ContainerWrapper) {
            $theContainer = $container->getContainer();
            if ($theContainer instanceof SystemContainerInterface) {
                return $theContainer;
            }
        }

        if ($container instanceof SystemContainerInterface) {
            return $container;
        }

        return self::createFromContainer($container);
    }

    /**
     * @template T
     * @param string|class-string<T> $id
     * @return T|mixed
     * @throws ContainerFrozenException
     * @throws ContainerNotFoundException
     * @throws Throwable
     */
    public function get(string $id)
    {
        if ($this->container instanceof SystemContainerInterface
            || $this->container->has($id)
        ) {
            return $this->container->get($id);
        }

        /** @noinspection DuplicatedCode */
        if ($this->hasRawService($id)) {
            $this->frozenServices[$id] ??= true;
            return $this->getRawService($id);
        }

        $exists = $this->hasQueuedService($id);
        if (!$exists && $this->hasAlias($id)) {
            $newId = $this->getAlias($id);
            if ($this->hasRawService($newId)) {
                $this->frozenServices[$id] ??= true;
                return $this->getRawService($newId);
            }
            if ($this->hasQueuedService($newId)) {
                $id = $newId;
                $exists = true;
            }
        }

        if ($exists) {
            try {
                // assert
                $this->assertCallstack();
                $value = $this->getResolver()->resolve($id);
                $this->removeQueuedService($id);
                // call
                $this->raw($id, $value);
                $this->frozenServices[$id] = true;
                // reset
                $this->resetCallstack();
                return $value;
            } catch (Throwable $e) {
                $this->resetCallstack();
                throw $e;
            }
        }

        throw new ContainerNotFoundException(
            sprintf('Container %s has not found.', $id)
        );
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function remove(string $id): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->remove($id);
            return;
        }

        unset(
            $this->queuedServices[$id],
            $this->frozenServices[$id],
            $this->rawServices[$id],
            $this->arguments[$id]
        );
    }

    public function set(string $id, mixed $container, ...$arguments): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->set($id, $container, ...$arguments);
            return;
        }

        if (method_exists($this->container, 'set')) {
            $this->container->set($id, function () use ($container, $arguments) {
                return $this->getResolver()->resolveCallable($container, $arguments);
            });
            return;
        }

        $this->assertFrozen($id);
        unset($this->rawServices[$id]);
        $this->queuedServices[$id] = $container;
        if ($arguments === []) {
            $this->arguments[$id] = $arguments;
        }
    }

    public function getResolver(): ContainerResolver
    {
        return $this->resolver??$this->container->getResolver();
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

    public function hasArgument(string $serviceId): bool
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->hasArgument($serviceId);
        }
        return array_key_exists($serviceId, $this->arguments);
    }

    public function getArgument(string $serviceId)
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getArgument($serviceId);
        }
        return $this->arguments[$serviceId]??null;
    }

    public function setAlias(string $id, string $containerId): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->setAlias($id, $containerId);
            return;
        }
        $this->aliases[$id] = $containerId;
    }

    public function removeAlias(string $id): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->removeAlias($id);
            return;
        }
        unset($this->aliases[$id]);
    }

    public function getAliases(): array
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getAliases();
        }
        return $this->aliases;
    }

    public function hasAlias(string $id): bool
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->hasAlias($id);
        }
        return array_key_exists($id, $this->aliases);
    }

    public function getAlias(string $id): ?string
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getAlias($id);
        }
        return $this->aliases[$id]??null;
    }

    public function getParameter(string $name)
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getParameter($name);
        }
        return $this->parameters[$name]??null;
    }

    public function getParameters(): array
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getParameters();
        }
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->setParameters($parameters);
            return;
        }
        $this->parameters = $parameters;
    }

    public function setParameter(string $name, #[SensitiveParameter] $value): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->setParameter($name, $value);
            return;
        }
        $this->parameters[$name] = $value;
    }

    public function add(ContainerInvokable $objectContainer, ...$arguments): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->add($objectContainer, ...$arguments);
            return;
        }
        $this->set($objectContainer->getId(), $objectContainer, ...$arguments);
    }

    public function raw(string $id, $raw): void
    {
        if ($this->container instanceof SystemContainerInterface) {
            $this->container->raw($id, $raw);
            return;
        }
        $this->assertFrozen($id);
        $this->rawServices[$id] = $raw;
    }

    public function getQueuedServices(): array
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getQueuedServices();
        }
        return $this->queuedServices;
    }

    public function hasQueuedService(string $id): bool
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->hasQueuedService($id);
        }
        return array_key_exists($id, $this->queuedServices);
    }

    public function removeQueuedService(string $id): mixed
    {
        $value = $this->getQueueService($id);
        unset($this->queuedServices[$id], $this->arguments[$id]);
        return $value;
    }

    public function inQueue(string $id): bool
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->inQueue($id);
        }
        return array_key_exists($id, $this->queuedServices);
    }

    public function getQueueService(string $id) : mixed
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getQueueService($id);
        }
        return $this->queuedServices[$id]??null;
    }

    public function isFrozen(string $id): bool
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->isFrozen($id);
        }
        return isset($this->frozenServices[$id]);
    }

    public function getRawServices(): array
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getRawServices();
        }
        return $this->rawServices;
    }

    public function getRawService(string $id)
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getRawServices();
        }
        return $this->rawServices[$id]??null;
    }

    public function hasRawService(string $id): bool
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->hasRawService($id);
        }
        return array_key_exists($id, $this->rawServices);
    }

    public function getFrozenServices(): array
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->getFrozenServices();
        }
        return $this->frozenServices;
    }

    public function keys() : array
    {
        if ($this->container instanceof SystemContainerInterface) {
            return $this->container->keys();
        }
        return array_merge(array_keys($this->queuedServices), array_keys($this->rawServices));
    }
}
