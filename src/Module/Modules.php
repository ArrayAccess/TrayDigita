<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Module;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Module\Interfaces\ModuleInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use function is_object;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * Modules storage.
 * Once module attached, it should not to be detached
 */
final class Modules implements ContainerIndicateInterface, ManagerIndicateInterface
{
    use ManagerAllocatorTrait;

    /**
     * @var array<class-string<ModuleInterface>, ModuleInterface>
     */
    private array $modules = [];

    /**
     * @var array<string, int>
     */
    private array $priorityRecords = [];

    private array $inits = [];

    private int $doingInit = 0;

    public function __construct(protected ContainerInterface $container)
    {
        $manager = ContainerHelper::use(ManagerInterface::class, $this->container);
        if ($manager) {
            $this->setManager($manager);
        }
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function getPriorityRecords(): array
    {
        return $this->priorityRecords;
    }

    /**
     * @param ModuleInterface|class-string<ModuleInterface> $module
     * @return bool
     */
    public function has(ModuleInterface|string $module) : bool
    {
        $moduleName = is_string($module) ? $module : $module::class;
        return isset($this->modules[strtolower($moduleName)]);
    }

    public function initModules(): array
    {
        $init = [];
        $this->doingInit++;
        asort($this->priorityRecords);
        $manager = $this->getManager();
        foreach ($this->priorityRecords as $record => $i) {
            if (isset($this->inits[$record])) {
                continue;
            }
            $this->inits[$record] = true;
            $module = ($this->modules[$record]??null);
            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException(
                    sprintf(
                        'Module %s has been unexpected override!',
                        $record
                    )
                );
            }
            try {
                $manager?->dispatch(
                    'module.beforeInit',
                    $module,
                    $this
                );
                $module->init();
                $init[$record] = $module::class;
                $manager?->dispatch(
                    'module.init',
                    $module,
                    $this
                );
            } finally {
                $manager?->dispatch(
                    'module.afterInit',
                    $module,
                    $this
                );
            }
        }

        return $init;
    }


    /**
     * @template T of ModuleInterface
     * @psalm-param class-string<T>|T $module
     * @psalm-return ?T
     */
    public function get(ModuleInterface|string $module) : ?ModuleInterface
    {
        $moduleName = is_string($module) ? $module : $module::class;
        return $this->modules[strtolower($moduleName)]??null;
    }

    private bool $inProcess = false;

    /**
     * @template T of ModuleInterface
     * @psalm-param T|class-string<T> $module
     * @psalm-return T
     */
    public function attach(ModuleInterface|string $module) : ?ModuleInterface
    {
        if ($this->inProcess || $this->has($module)) {
            return null;
        }

        $object = is_object($module);
        if ($object && $module instanceof ModuleInterface) {
            $name = strtolower($module::class);
            if (isset($this->modules[$name])) {
                return null;
            }
            $this->priorityRecords[$name] = $module->getPriority();
            return $this->modules[$name] = $module;
        }

        if ($object) {
            throw new InvalidArgumentException(
                sprintf(
                    'Class "%s" should be subclass of %s',
                    $module,
                    ModuleInterface::class
                )
            );
        }
        if (isset($this->modules[strtolower($module)])) {
            return null;
        }
        $this->inProcess = true;
        $module = new $module($this);
        $this->inProcess = false;
        $name = strtolower($module::class);
        $this->priorityRecords[$name] = $module->getPriority();
        return $this->modules[$name] = $module;
    }
}
