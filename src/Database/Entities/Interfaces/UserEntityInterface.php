<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Interfaces;

interface UserEntityInterface extends
    UserStatusEntityInterface,
    IdentityBasedEntityInterface,
    RoleBasedEntityInterface
{
    public function getUsername();

    public function getEmail();
}
