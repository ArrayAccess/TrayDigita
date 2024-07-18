<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles\Interfaces;

interface CapabilityInterface
{
    /**
     * Check if the capability has a role
     *
     * @param RoleInterface|string $role
     * @return bool
     */
    public function has(RoleInterface|string $role) : bool;

    /**
     * Add a role to the capability
     * @param RoleInterface|string $role
     */
    public function add(RoleInterface|string $role);

    /**
     * Get role identity
     *
     * @return string
     */
    public function getIdentity(): string;

    /**
     * Get capability name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get capability description
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Get list of roles
     *
     * @return iterable<string, RoleInterface>
     */
    public function getRoles() : iterable;
}
