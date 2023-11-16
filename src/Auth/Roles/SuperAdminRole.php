<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles;

final class SuperAdminRole extends AbstractRole
{
    public const NAME = 'superadmin';

    protected string $identity = self::NAME;

    protected string $name = 'Super admin';

    protected ?string $description = 'Super admin role';
}
