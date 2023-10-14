<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles;

use function strtolower;
use function trim;

class InstanceRole extends AbstractRole
{
    public function __construct(
        string $identity,
        protected string $name,
        protected ?string $description = null
    ) {
        $this->identity = strtolower(trim($identity));
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
