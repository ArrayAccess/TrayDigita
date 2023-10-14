<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles\Interfaces;

interface CapabilityInterface
{
    public function has(RoleInterface|string $role) : bool;

    public function getIdentity(): string;

    public function getName(): string;

    public function getDescription(): ?string;

    public function getRoles() : iterable;
}
