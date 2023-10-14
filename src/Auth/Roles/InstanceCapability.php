<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles;

class InstanceCapability extends AbstractCapability
{
    public function __construct(
        protected string $identity,
        protected string $name,
        protected ?string $description = null
    ) {
        parent::__construct();
    }

    public static function create(
        string $identity,
        string $name,
        ?string $description = null
    ) : static {
        return new static($identity, $name, $description);
    }
}
