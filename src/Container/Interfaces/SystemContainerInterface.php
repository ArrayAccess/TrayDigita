<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Interfaces;

use ArrayAccess;
use ArrayAccess\TrayDigita\Container\ContainerInvokable;
use ArrayAccess\TrayDigita\Container\ContainerResolver;
use ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException;
use Psr\Container\ContainerInterface;
use SensitiveParameter;

interface SystemContainerInterface extends ContainerInterface, ArrayAccess, UnInvokableInterface
{
    public function getResolver(): ContainerResolver;

    public function hasArgument(string $serviceId) : bool;

    public function getArgument(string $serviceId);

    public function setAlias(string $id, string $containerId);

    public function removeAlias(string $id);

    public function getAliases(): array;

    public function hasAlias(string $id) : bool;

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

    public function remove(string $id);

    /**
     * @throws ContainerFrozenException
     */
    public function raw(string $id, $raw);

    public function getQueuedServices() : array;

    public function hasQueuedService(string $id): bool;

    public function removeQueuedService(string $id);

    public function inQueue(string $id): bool;

    public function getQueueService(string $id);

    public function isFrozen(string $id): bool;

    public function getRawServices(): array;

    public function getRawService(string $id);

    public function hasRawService(string $id) : bool;

    public function getFrozenServices(): array;

    public function keys() : array;
}
