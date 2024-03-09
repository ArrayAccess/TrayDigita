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
    /**
     * @var string Module name
     */
    protected string $name = '';

    /**
     * @var ?string Module description
     */
    protected ?string $description = '';

    /**
     * @var bool Module is important
     */
    protected bool $important = false;

    /**
     * @var int Module priority
     */
    protected int $priority = ModuleInterface::DEFAULT_PRIORITY;

    /**
     * @var bool Module is initialized
     */
    private bool $init = false;

    /**
     * Getting kernel from container
     *
     * @return KernelInterface
     */
    public function getKernel() : KernelInterface
    {
        return ContainerHelper::service(
            KernelInterface::class,
            $this->getContainer()
        );
    }

    /**
     * Get module is important
     *
     * @return bool Module is important
     */
    public function isImportant(): bool
    {
        return $this->important;
    }

    /**
     * Get container
     *s
     * @return ContainerInterface Container
     */
    public function getContainer(): ContainerInterface
    {
        return $this->getModules()->getContainer();
    }

    /**
     * Get manager
     *
     * @return ManagerInterface Manager
     */
    public function getManager(): ManagerInterface
    {
        $manager = $this->getModules()->getManager();
        $manager ??= ContainerHelper::use(
            ManagerInterface::class,
            $this->getContainer()
        )??Decorator::manager();
        return $manager;
    }

    /**
     * Get module name
     *
     * @return string Module name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get module description
     *
     * @return ?string Module description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Doing initialization
     *
     * @return void
     * @final Module initialization
     */
    final public function init(): void
    {
        if ($this->init) {
            return;
        }
        $this->doInit();
    }

    /**
     * Get module priority
     * @return int Module priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Method (override by children) when module is initialized
     */
    protected function doInit()
    {
        // pass
    }

    /**
     * @return Modules Modules
     */
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
