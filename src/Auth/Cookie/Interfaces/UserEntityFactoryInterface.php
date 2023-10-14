<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Cookie\Interfaces;

use ArrayAccess\TrayDigita\Database\Entities\Interfaces\UserEntityInterface;

interface UserEntityFactoryInterface
{
    public function findById(int $id) : ?UserEntityInterface;

    public function findByUsername(string $username) : ?UserEntityInterface;
}
