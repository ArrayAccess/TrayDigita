<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Module\Traits;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Module\Interfaces\ModuleInterface;
use ArrayAccess\TrayDigita\Module\Modules;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;

trait ModuleTrait
{
    protected string $name = '';

    protected ?string $description = '';

    protected bool $important = false;

    protected int $priority = ModuleInterface::DEFAULT_PRIORITY;

    private bool $init = false;

    public function getKernel()
    {
        return ContainerHelper::use(
            KernelInterface::class,
            $this->getContainer()
        );
    }

    public function isImportant(): bool
    {
        return $this->important;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->getModules()->getContainer();
    }

    public function getManager(): ManagerInterface
    {
        $manager = $this->getModules()->getManager();
        $manager ??= ContainerHelper::use(
            ManagerInterface::class,
            $this->getContainer()
        )??Decorator::manager();
        return $manager;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    final public function init(): void
    {
        if ($this->init) {
            return;
        }
        $this->doInit();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    protected function doInit()
    {
        // pass
    }

    public function getModules(): Modules
    {
        return $this->modules;
    }

    /**
     * @template T of ModuleInterface
     * @psalm-param class-string<T>|T $module
     * @psalm-return ?T
     * @return ?T
     */
    public function getModule(ModuleInterface|string $module)
    {
        return $this->getModules()->get($module);
    }
}
