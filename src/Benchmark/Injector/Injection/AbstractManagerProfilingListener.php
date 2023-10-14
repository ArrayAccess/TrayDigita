<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

use ArrayAccess\TrayDigita\Benchmark\Injector\ManagerProfiler;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use function array_shift;
use function explode;
use function is_string;

abstract class AbstractManagerProfilingListener
{
    /**
     * @var array{string: array{0:string, 1:string}|false}
     */
    private static array $cachedData = [];

    final public function __construct(protected ManagerProfiler $profilerManager)
    {
        $this->onConstruct();
    }

    protected function onConstruct()
    {
    }

    public function getProfilerManager(): ManagerProfiler
    {
        return $this->profilerManager;
    }

    /**
     * @param string $eventName
     * @return ?array{0:string, 1:string}
     */
    protected function getDefinition(string $eventName): ?array
    {
        if (!isset(self::$cachedData[$eventName])) {
            $explode = explode('.', $eventName, 2);
            $group   = array_shift($explode);
            $name    = array_shift($explode);
            self::$cachedData[$eventName] = false;
            if (is_string($group)) {
                self::$cachedData[$eventName] = [$group, $name];
            }
        }

        return self::$cachedData[$eventName] !== false
            ? self::$cachedData[$eventName]
            : null;
    }

    public function getEventName(string $eventName): ?string
    {
        $definition = $this->getDefinition($eventName);
        return $definition !== null
            ? $definition[1]
            : null;
    }

    public function getEventGroup(string $eventName): ?string
    {
        $definition = $this->getDefinition($eventName);
        return $definition !== null ? $definition[0] : null;
    }

    /**
     * Start listener
     *
     * @param ManagerProfiler $managerProfiler
     * @param ManagerInterface $manager
     * @param string $eventName
     * @param int|null $priority
     * @param string|null $id
     * @param $originalParam
     * @param $param
     * @param ...$arguments
     * @return ?$this
     */
    abstract public function start(
        ManagerProfiler $managerProfiler,
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        $originalParam,
        $param,
        ...$arguments
    ) : ?static;

    /**
     * Doing stop listener if returning current object
     *
     * @param ManagerProfiler $managerProfiler
     * @param ManagerInterface $manager
     * @param string $eventName
     * @param int|null $priority
     * @param string|null $id
     * @param $originalParam
     * @param $param
     * @param ...$arguments
     */
    abstract public function stop(
        ManagerProfiler $managerProfiler,
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        $originalParam,
        $param,
        ...$arguments
    );
}
