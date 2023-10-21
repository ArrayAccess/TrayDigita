<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Kernel;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\PermissionInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\ContainerWrapper;
use ArrayAccess\TrayDigita\Container\Factory\ContainerFactory;
use ArrayAccess\TrayDigita\Container\Interfaces\SystemContainerInterface;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\EmptyArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\ServiceLockedException;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Module\Interfaces\ModuleInterface;
use ArrayAccess\TrayDigita\Module\Modules;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Doctrine\Persistence\ObjectRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function array_search;
use function end;
use function key;
use function reset;
use function sprintf;

final class Decorator
{
    const DEFAULT_NAME = 'default';

    protected Kernel $defaultKernel;

    /**
     * @var array<string, AbstractKernel>
     */
    protected array $kernels = [];

    /**
     * @var ?Decorator
     */
    protected static ?Decorator $instance = null;

    /**
     * @var string
     */
    protected string $currentAppName = self::DEFAULT_NAME;

    /**
     * @var array<string, bool>
     */
    protected array $previousSet = [];

    protected ?string $locked = null;

    public function __construct()
    {
        self::$instance = $this;
        $this->defaultKernel = new Kernel();
        $this->setKernel(self::DEFAULT_NAME, $this->defaultKernel);
    }

    private function assertEmpty(string $appName): void
    {
        if ($appName === '') {
            throw new EmptyArgumentException(
                'Application name could not being empty.'
            );
        }
    }

    private function assertLocked(string $appName): void
    {
        if ($this->locked !== $appName
            && isset($this->kernels[$this->locked])
        ) {
            throw new ServiceLockedException(
                sprintf(
                    'Kernel decorator service already locked with : %s',
                    $this->locked
                )
            );
        }
    }

    private function assertRemoveLocked(string $appName): void
    {
        if ($this->locked === $appName
            && isset($this->kernels[$this->locked])
        ) {
            throw new ServiceLockedException(
                sprintf(
                    'Kernel decorator service already locked with : %s',
                    $this->locked
                )
            );
        }
    }

    public static function getLockedServiceName() : ?string
    {
        return self::decorator()->locked;
    }

    public static function isLocked() : bool
    {
        return self::decorator()->locked !== null;
    }

    public static function findKernelName(AbstractKernel $kernel): ?string
    {
        return array_search($kernel, self::decorator()->kernels)?:null;
    }

    public static function init(): AbstractKernel
    {
        return self::lock()->init();
    }

    public static function lock(?string $appName = null): AbstractKernel|null
    {
        $instance = self::decorator();
        if ($appName === null && $instance->locked) {
            return $instance->kernels[$instance->locked];
        }

        $appName ??= $instance->currentAppName;
        $instance->assertLocked($appName);
        if (!isset($instance->kernels[$appName])) {
            throw new RuntimeException(
                sprintf(
                    'Service %s does not exists',
                    $appName
                )
            );
        }

        $instance->locked = $appName;
        $instance->currentAppName = $instance->locked;
        return $instance->kernels[$instance->locked];
    }

    public function setKernel(
        string $appName,
        AbstractKernel $kernel
    ): Decorator {
        if ($this->locked === $appName
            && $this->kernels[$appName] === $kernel
        ) {
            return $this;
        }

        $this->assertEmpty($appName);
        $this->assertRemoveLocked($appName);
        if (empty($this->kernels)) {
            $this->currentAppName = $appName;
            $this->previousSet[$appName] = $appName;
        }

        $this->kernels[$appName] = $kernel;
        return $this;
    }

    public function removeKernel(string $appName) : ?AbstractKernel
    {
        if (!isset($this->kernels[$appName])) {
            return null;
        }

        $this->assertRemoveLocked($appName);

        $kernel = $this->kernels[$appName];
        unset($this->previousSet[$appName], $this->kernels[$appName]);

        if ($this->locked) {
            $this->currentAppName = $this->locked;
            return $this->kernels[$this->locked];
        }
        $current = end($this->previousSet);
        if ($current === false) {
            $current = self::DEFAULT_NAME;
        }

        $this->currentAppName = $current;
        if (empty($this->kernels)) {
            $this->previousSet = [];
            $this->currentAppName = self::DEFAULT_NAME;
            $this->setKernel(
                $this->currentAppName,
                $this->defaultKernel
            );
        }
        if (!isset($this->kernels[$this->currentAppName])) {
            reset($this->kernels);
            $this->currentAppName = key($this->kernels);
        }
        return $kernel;
    }

    public static function kernels() : array
    {
        return self::decorator()->kernels;
    }

    public function has(string $appName): bool
    {
        return isset($this->kernels[$appName]);
    }

    public function get(string $appName): ?AbstractKernel
    {
        return $this->kernels[$appName]??null;
    }

    public function use(string $appName): ?AbstractKernel
    {
        if ($this->has($appName) && !$this->locked) {
            $this->currentAppName = $appName;
            unset($this->previousSet[$appName]);
            $this->previousSet[$appName] = $appName;
        }
        return $this->kernels[$appName]??null;
    }

    public function current(): AbstractKernel
    {
        if (empty($this->kernels)) {
            $this->currentAppName = $this->locked??self::DEFAULT_NAME;
            $this->setKernel(
                $this->currentAppName,
                $this->defaultKernel
            );
        }
        return $this->kernels[$this->currentAppName];
    }

    /**
     * Get current kernel
     *
     * @return ?AbstractKernel
     */
    public static function kernel() : ?AbstractKernel
    {
        return self::decorator()->current();
    }

    public static function decorator() : Decorator
    {
        self::$instance??= new self();
        return self::$instance;
    }

    /**
     * @template T of Object
     * @param string $name
     * @return mixed|T
     */
    public static function service(string $name)
    {
        return self::resolveDepend($name);
    }

    private static function resolveInternal(string $name): SystemContainerInterface
    {
        $container = self::container();
        $container = ContainerWrapper::maybeContainerOrCreate($container);
        if (isset(ContainerFactory::DEFAULT_SERVICES[$name])) {
            $has = $container->has($name);
            try {
                if ($has && !$container->get($name) instanceof $name) {
                    $has = false;
                    $container->remove($name);
                }
            } catch (Throwable) {
            }
            if (!$has) {
                $container->set($name, ContainerFactory::DEFAULT_SERVICES[$name]);
            }
        }
        return $container;
    }

    /**
     * @template T of object
     * @param class-string<T> $name
     * @return ?T
     */
    private static function resolveDepend(string $name)
    {
        try {
            return self::resolveInternal($name)->get($name);
        } catch (Throwable) {
            return null;
        }
    }

    public static function router() : RouterInterface
    {
        return self::kernel()->getRouter();
    }

    public static function container() : ContainerInterface
    {
        return self::kernel()->getContainer();
    }

    public static function database() : Connection
    {
        return self::resolveDepend(Connection::class);
    }

    public static function translator() : TranslatorInterface
    {
        return self::resolveDepend(TranslatorInterface::class);
    }

    public static function cache() : CacheItemPoolInterface
    {
        return self::resolveDepend(CacheItemPoolInterface::class);
    }

    public static function logger() : LoggerInterface
    {
        return self::resolveDepend(LoggerInterface::class);
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return ObjectRepository<T>
     */
    public static function entityRepository(string $className) : ObjectRepository
    {
        return self::database()->getRepository($className);
    }

    public static function config() : Config
    {
        return self::resolveDepend(Config::class);
    }

    public static function manager() : ManagerInterface
    {
        return self::resolveDepend(ManagerInterface::class);
    }

    public static function view() : ViewInterface
    {
        return self::resolveDepend(ViewInterface::class);
    }

    public static function benchmark() : ProfilerInterface
    {
        return self::resolveDepend(ProfilerInterface::class);
    }

    public static function scheduler() : Scheduler
    {
        return self::resolveDepend(Scheduler::class);
    }

    public static function permission() : PermissionInterface
    {
        return self::resolveDepend(PermissionInterface::class);
    }

    public static function modules() : Modules
    {
        return self::resolveDepend(Modules::class);
    }

    /**
     * @template T of ModuleInterface
     * @psalm-param T|class-string<T> $module
     * @psalm-return ?T
     */
    public static function module(string|ModuleInterface $module)
    {
        return self::modules()->get($module);
    }
}
