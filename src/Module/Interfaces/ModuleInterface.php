<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Module\Interfaces;

use ArrayAccess\TrayDigita\Module\Modules;

interface ModuleInterface
{
    const DEFAULT_PRIORITY = 10;

    public function __construct(Modules $modules);

    public function getName() : string;

    public function getDescription() : ?string;

    public function getModules(): Modules;

    public function getPriority() : int;

    public function isImportant(): bool;

    public function isCore(): bool;

    public function init();
}
