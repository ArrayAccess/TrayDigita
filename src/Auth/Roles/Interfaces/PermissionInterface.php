<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles\Interfaces;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;

interface PermissionInterface extends ManagerIndicateInterface
{
    public function permitted(RoleInterface|UserRoleInterface $role, string|CapabilityInterface $capability) : bool;

    public function add(CapabilityInterface $capability): CapabilityInterface;

    public function replace(CapabilityInterface $capability);

    public function has(string|CapabilityInterface $identity): bool;

    public function get(string|CapabilityInterface $identity): ?CapabilityInterface;

    public function remove(string|CapabilityInterface $identity): ?CapabilityInterface;

    public function identify(string|CapabilityInterface $capability): string;

    /**
     * @return iterable<string, CapabilityInterface>
     */
    public function getCapabilities(): iterable;
}
