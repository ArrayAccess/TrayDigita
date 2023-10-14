<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles\Interfaces;

use Serializable;
use Stringable;

interface RoleInterface extends Serializable, Stringable
{
    public function getRole() : string;
}
