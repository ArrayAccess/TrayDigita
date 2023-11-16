<?php
/** @noinspection PhpDeprecationInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Event;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Benchmark\Traits\ProfilingTrait;
use ArrayAccess\TrayDigita\Event\Interfaces\EventDispatchListenerInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;

/**
 * @removed
 * @deprecated
 */
class BenchmarkEventDispatchListener implements EventDispatchListenerInterface
{
    public const GROUP_NAME = 'manager';

    use ProfilingTrait;

    /**
     * @var array<RecordInterface[]>
     */
    private array $benchmarkRecordLists = [];

    public function __construct(protected ProfilerInterface $profiler)
    {
    }

    public function getProfiler(): ProfilerInterface
    {
        return $this->profiler;
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
        if ($manager->insideOf($eventName)) {
            $this->benchmarkRecordLists[$eventName][$id] ??= $this->benchmarkStart(
                name: $eventName,
                group: self::GROUP_NAME,
                context: [
                    'priority' => $priority,
                    'id' => $id
                ],
            );
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
        if (!isset($this->benchmarkRecordLists[$eventName])) {
            return;
        }

        $this->benchmarkRecordLists[$eventName][$id]?->stop();
        unset($this->benchmarkRecordLists[$eventName][$id]);

        if (empty($this->benchmarkRecordLists[$eventName])) {
            unset($this->benchmarkRecordLists[$eventName]);
        }
    }
}
