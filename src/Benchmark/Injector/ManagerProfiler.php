<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector;

use ArrayAccess\TrayDigita\Benchmark\Injector\Injection\AbstractManagerProfilingListener;
use ArrayAccess\TrayDigita\Benchmark\Injector\Injection\DatabaseProfilerInjector;
use ArrayAccess\TrayDigita\Benchmark\Injector\Injection\KernelProfilerInjector;
use ArrayAccess\TrayDigita\Benchmark\Injector\Injection\ManagerProfilerInjector;
use ArrayAccess\TrayDigita\Benchmark\Injector\Injection\MiddlewareProfilerInjector;
use ArrayAccess\TrayDigita\Benchmark\Injector\Injection\RouteProfilerInjector;
use ArrayAccess\TrayDigita\Benchmark\Injector\Injection\ServicesProfilerInjector;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\EventDispatchListenerInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use function is_string;
use function is_subclass_of;
use function spl_object_hash;

class ManagerProfiler implements EventDispatchListenerInterface
{
    /**
     * @var array<string, AbstractManagerProfilingListener>
     */
    private array $listeners;

    /**
     * @var array<string, array<string, AbstractManagerProfilingListener>>
     */
    private array $listened = [];

    /**
     * Profiler Default
     */
    const PROVIDERS = [
        DatabaseProfilerInjector::class,
        KernelProfilerInjector::class,
        MiddlewareProfilerInjector::class,
        RouteProfilerInjector::class,
        ServicesProfilerInjector::class,
        ManagerProfilerInjector::class
    ];

    private bool $providerRegistered = false;

    public function __construct(
        protected ManagerInterface $manager,
        protected ProfilerInterface $profiler
    ) {
        $this->listeners = [];
    }

    public function getManager(): ManagerInterface
    {
        return $this->manager;
    }

    public function setProfiler(ProfilerInterface $profiler): void
    {
        $this->profiler = $profiler;
    }

    public function getProfiler(): ProfilerInterface
    {
        return $this->profiler;
    }

    protected function getProviderClasses(): array
    {
        return static::PROVIDERS;
    }

    public function registerProviders(): void
    {
        if ($this->providerRegistered) {
            return;
        }
        $this->providerRegistered = true;
        $listenerClassName = [];
        foreach ($this->listeners as $listener) {
            $listenerClassName[$listener::class] = true;
        }
        foreach (self::PROVIDERS as $className) {
            if (!is_string($className) || !is_subclass_of($className, AbstractManagerProfilingListener::class)) {
                continue;
            }
            if (isset($listenerClassName[$className])) {
                continue;
            }
            $this->add(new $className($this));
        }
    }

    public function add(AbstractManagerProfilingListener $listener): void
    {
        $this->listeners[spl_object_hash($listener)] = $listener;
    }

    /**
     * Prepend the listener
     *
     * @param AbstractManagerProfilingListener $listener
     * @return void
     */
    public function prepend(AbstractManagerProfilingListener $listener): void
    {
        $this->listeners = [spl_object_hash($listener) => $listener] + $this->listeners;
    }

    public function remove(AbstractManagerProfilingListener $listener): void
    {
        unset($this->listeners[spl_object_hash($listener)]);
    }

    public function has(AbstractManagerProfilingListener $listener): bool
    {
        return isset($this->listeners[spl_object_hash($listener)]);
    }

    /**
     * @return array<AbstractManagerProfilingListener>
     */
    public function getListeners() : array
    {
        return $this->listeners;
    }

    public function onBeforeDispatch(
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        $originalParam,
        $param,
        ...$arguments
    ): void {
        foreach ($this->listeners as $key => $listener) {
            $listener = $listener->start(
                $this,
                $manager,
                $eventName,
                $priority,
                $id,
                $originalParam,
                $param,
                ...$arguments
            );
            if ($listener) {
                $this->listened[$id][$key] = $listener;
            }
        }
    }

    public function onFinishDispatch(
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        $originalParam,
        $param,
        ...$arguments
    ): void {
        if (!isset($this->listened[$id])) {
            return;
        }
        foreach ($this->listened[$id] as $key => $profiler) {
            unset($this->listened[$id][$key]);
            $profiler->stop(
                $this,
                $manager,
                $eventName,
                $priority,
                $id,
                $originalParam,
                $param,
                ...$arguments
            );
        }

        if (empty($this->listened[$id])) {
            unset($this->listened[$id]);
        }
    }
}
