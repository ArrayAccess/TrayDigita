<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;

final class SuperAdminCapability extends AbstractCapability
{
    const NAME = 'superadmin';

    protected string $identity = self::NAME;

    protected string $name = 'Super Admin';

    protected ?string $description = 'Super admin capabilities';

    public function __construct()
    {
        $this->initRoles = [new SuperAdminRole()];
        parent::__construct();
    }

    public function add(RoleInterface|string $role): null
    {
        if ($role instanceof SuperAdminRole
            && ! $this->has($role)
        ) {
            parent::add($role);
        }

        return null;
    }

    public function remove(RoleInterface|string $role): void
    {
        // void
    }
}
