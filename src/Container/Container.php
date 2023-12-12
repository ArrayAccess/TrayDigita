<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container;

use ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException;
use ArrayAccess\TrayDigita\Container\Exceptions\ContainerNotFoundException;
use ArrayAccess\TrayDigita\Container\Interfaces\SystemContainerInterface;
use ArrayAccess\TrayDigita\Container\Traits\ContainerDecorator;
use ArrayAccess\TrayDigita\Exceptions\Logical\InvokeAbleException;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use SensitiveParameter;
use Throwable;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function sprintf;

/**
 * @template T of object
 */
class Container implements SystemContainerInterface
{
    use CallStackTraceTrait,
        ContainerDecorator;

    /**
     * @var array
     */
    private array $aliases = [];

    private array $queuedServices = [];

    private array $arguments = [];

    private array $parameters = [];

    private array $rawServices = [];

    private array $frozenServices = [];

    private ContainerResolver $resolver;

    public function __construct()
    {
        $this->resolver = new ContainerResolver($this);
    }

    public function getResolver(): ContainerResolver
    {
        return $this->resolver;
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

    public function setAlias(string $id, string $containerId): void
    {
        $this->aliases[$id] = $containerId;
    }

    public function hasArgument(string $serviceId) : bool
    {
        return array_key_exists($serviceId, $this->arguments);
    }

    public function getArgument(string $serviceId) : ?array
    {
        return $this->arguments[$serviceId]??null;
    }

    public function removeAlias(string $id): void
    {
        unset($this->aliases[$id]);
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function hasAlias(string $id) : bool
    {
        return isset($this->aliases[$id]);
    }

    public function getAlias(string $id) : ?string
    {
        return $this->aliases[$id]??null;
    }

    public function getParameter(string $name)
    {
        return $this->parameters[$name]??null;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function setParameter(string $name, #[SensitiveParameter] $value): void
    {
        $this->parameters[$name] = $value;
    }

    /**
     * @param string $id
     * @param callable|class-string<T>|mixed $container
     * @param ...$arguments
     * @throws ContainerFrozenException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function set(string $id, mixed $container, ...$arguments)
    {
        $this->assertFrozen($id);
        unset($this->rawServices[$id]);
        $this->queuedServices[$id] = $container;
        if ($arguments === []) {
            $this->arguments[$id] = $arguments;
        }
    }

    /**
     * @throws ContainerFrozenException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function add(ContainerInvokable $objectContainer, ...$arguments)
    {
        $this->set($objectContainer->getId(), $objectContainer, ...$arguments);
    }

    /**
     * @param string $id
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function remove(string $id)
    {
        unset(
            $this->queuedServices[$id],
            $this->frozenServices[$id],
            $this->rawServices[$id],
            $this->arguments[$id]
        );
    }

    /**
     * @throws ContainerFrozenException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function raw(string $id, $raw)
    {
        $this->assertFrozen($id);
        $this->rawServices[$id] = $raw;
    }

    public function getQueuedServices() : array
    {
        return $this->queuedServices;
    }

    public function hasQueuedService(string $id): bool
    {
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
        return array_key_exists($id, $this->queuedServices);
    }

    public function getQueueService(string $id)
    {
        return $this->queuedServices[$id]??null;
    }

    public function isFrozen(string $id): bool
    {
        return isset($this->frozenServices[$id]);
    }

    public function getRawServices(): array
    {
        return $this->rawServices;
    }

    public function getRawService(string $id)
    {
        return $this->rawServices[$id]??null;
    }

    public function hasRawService(string $id) : bool
    {
        return array_key_exists($id, $this->rawServices);
    }

    public function getFrozenServices(): array
    {
        return array_keys($this->frozenServices);
    }

    /**
     * @param string|class-string<T> $id
     * @return T|mixed
     * @throws ContainerFrozenException
     * @throws ContainerNotFoundException
     * @throws Throwable
     */
    public function get(string $id)
    {
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

    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->rawServices)
            || array_key_exists($id, $this->queuedServices)
            || (
                isset($this->aliases[$id])
                && (
                    array_key_exists($this->aliases[$id], $this->rawServices)
                    || array_key_exists($this->aliases[$id], $this->queuedServices)
                )
            );
    }

    public function keys() : array
    {
        return array_merge(array_keys($this->queuedServices), array_keys($this->rawServices));
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @inheritdoc
     * @throws \Psr\Container\ContainerExceptionInterface|Throwable
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @throws ContainerFrozenException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string) $offset);
    }

    public function __invoke()
    {
        throw new InvokeAbleException(
            sprintf('Class %s is not invokable', $this::class)
        );
    }

    /**
     * For print_r debug
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return Consolidation::debugInfo($this, ['parameters', 'arguments']);
    }
}
