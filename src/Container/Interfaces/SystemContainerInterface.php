<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Interfaces;

use ArrayAccess;
use ArrayAccess\TrayDigita\Container\ContainerInvokable;
use ArrayAccess\TrayDigita\Container\ContainerResolver;
use Psr\Container\ContainerInterface;
use SensitiveParameter;

/**
 * @template ContainerFrozenException of \ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
interface SystemContainerInterface extends ContainerInterface, ArrayAccess, UnInvokableInterface
{
    /**
     * Get the container resolver
     *
     * @return ContainerResolver
     */
    public function getResolver(): ContainerResolver;

    /**
     * Check if the container has a service argument
     *
     * @param string $serviceId
     * @return bool
     */
    public function hasArgument(string $serviceId) : bool;

    /**
     * Get the service argument
     *
     * @param string $serviceId
     */
    public function getArgument(string $serviceId);

    /**
     * Set the container alias
     *
     * @param string $id
     * @param string $containerId
     */
    public function setAlias(string $id, string $containerId);

    /**
     * Remove container alias
     *
     * @param string $id
     */
    public function removeAlias(string $id);

    /**
     * Get the aliases
     *
     * @return array<string, string> the aliases
     */
    public function getAliases(): array;

    /**
     * Check if the container has an alias
     *
     * @param string $id
     * @return bool
     */
    public function hasAlias(string $id) : bool;

    /**
     * Get the alias
     *
     * @param string $id
     * @return string|null
     */
    public function getAlias(string $id) : ?string;

    public function getParameter(string $name);

    public function getParameters(): array;

    public function setParameters(array $parameters);

    public function setParameter(string $name, #[SensitiveParameter] $value);

    public function set(string $id, mixed $container, ...$arguments);

    /**
     * @throws ContainerFrozenException
     */
    public function add(ContainerInvokable $objectContainer, ...$arguments);

    /**
     * Remove the container
     *
     * @param string $id the container id
     */
    public function remove(string $id);

    /**
     * Set the raw container
     *
     * @throws ContainerFrozenException
     */
    public function raw(string $id, $raw);

    /**
     * Get the queued services
     *
     * @return array
     */
    public function getQueuedServices() : array;

    /**
     * Check if the container has a queued service
     *
     * @param string $id
     * @return bool
     */
    public function hasQueuedService(string $id): bool;

    /**
     * Remove the queued service
     *
     * @param string $id
     */
    public function removeQueuedService(string $id);

    /**
     * Check if the container id in queue
     *
     * @param string $id
     * @return bool
     */
    public function inQueue(string $id): bool;

    /**
     * Get Queue service
     *
     * @param string $id
     */
    public function getQueueService(string $id);

    /**
     * Check if the container is frozen
     *
     * @param string $id
     * @return bool
     */
    public function isFrozen(string $id): bool;

    /**
     * Get the raw services
     *
     * @return array
     */
    public function getRawServices(): array;

    /**
     * Get the raw service
     *
     * @param string $id
     */
    public function getRawService(string $id);

    /**
     * Check if the container has a raw service
     *
     * @param string $id
     * @return bool
     */
    public function hasRawService(string $id) : bool;

    /**
     * Get the frozen services
     *
     * @return array
     */
    public function getFrozenServices(): array;

    /**
     * Get key list of container
     *
     * @return array
     */
    public function keys() : array;
}
