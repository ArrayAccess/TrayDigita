<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles\Interfaces;

interface UserRoleInterface
{
    public function getObjectRole() : RoleInterface;

    public function getRole() : string;
}
