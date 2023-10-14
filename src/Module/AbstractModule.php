<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Module;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Module\Interfaces\ModuleInterface;
use ArrayAccess\TrayDigita\Module\Traits\ModuleTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;

abstract class AbstractModule implements ModuleInterface, ContainerIndicateInterface, ManagerIndicateInterface
{
    use ModuleTrait;

    final public function __construct(public readonly Modules $modules)
    {
        if (!$this->modules->has($this)) {
            $this->modules->attach($this);
        }
        if ($this->name === '') {
            $this->name = Consolidation::classShortName($this::class);
        }
    }

    final public function isCore(): bool
    {
        return false;
    }
}
