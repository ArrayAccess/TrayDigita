<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

use ArrayAccess\TrayDigita\Benchmark\Injector\ManagerProfiler;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use SensitiveParameter;
use function array_merge;
use function dirname;
use function is_float;
use function lcfirst;
use function microtime;
use function sprintf;
use function substr;
use function trim;

abstract class AbstractBasedCoreInjector extends AbstractManagerProfilingListener
{
    /**
     * @var array<string, RecordInterface>
     */
    protected array $recordsBenchmarks = [];

    /**
     * @var array<string, array<string, RecordInterface>>
     */
    protected array $singleBenchmarks = [];

    protected static array $listenedBenchmark = [];

    private array $startBenchmarks = [];

    private ?string $coreDirectory = null;

    protected bool $coreOnly = true;

    abstract protected function isAllowedGroup(string $group) : bool;

    protected function isAllowedName(string $group, string $name) : bool
    {
        return $this->getProfilerManager()->getProfiler()->isEnable()
            && $this->isAllowedGroup($group)
            && trim($name) !== ''
            && !str_contains($name, '.');
    }

    protected function getBenchmarkGroupName() : ?string
    {
        return null;
    }

    protected function appendToRecordStatic(string $eventName, ?string $id): void
    {
        $id ??= '_';
        if (isset(self::$listenedBenchmark[$eventName][$id])) {
            self::$listenedBenchmark[$eventName][$id]++;
            return;
        }
        if (!$this->appendToRecord()) {
            return;
        }
        self::$listenedBenchmark[$eventName][$id] ??= 0;
        self::$listenedBenchmark[$eventName][$id]++;
    }

    protected function getStaticRecord(string $eventName, ?string $id) : int
    {
        $id ??= '_';
        return self::$listenedBenchmark[$eventName][$id]??0;
    }

    protected function appendToRecord() : bool
    {
        return $this->getProfilerManager()->getProfiler()->isEnable();
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function acceptedRecord(ManagerInterface $manager, string $eventName, ?string $id): bool
    {
        if (!$this->getProfilerManager()->getProfiler()->isEnable()) {
            return false;
        }

        if ($this->coreOnly) {
            $trace = ($manager->getDispatcherTrace($eventName) ?? [])['file'] ?? null;
            if (!$trace) {
                return false;
            }
            $this->coreDirectory ??= dirname(__DIR__, 3);
            if (!str_starts_with($trace, $this->coreDirectory)) {
                return false;
            }
        }

        $name = $this->getEventName($eventName);
        $group = $this->getEventGroup($eventName);
        return ($group
            && $name
            && $this->isAllowedName($group, $name)
            && $this->isAllowedGroup($group)
        );
    }

    /**
     * @inheritdoc
     */
    public function start(
        ManagerProfiler $managerProfiler,
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        #[SensitiveParameter] $originalParam,
        #[SensitiveParameter] $param,
        #[SensitiveParameter] ...$arguments
    ): ?static {
        if (!$this->acceptedRecord($manager, $eventName, $id)) {
            return null;
        }
        $profiler = $this->getProfilerManager()->getProfiler();
        $group = $this->getEventGroup($eventName);
        $groupName = $this->getBenchmarkGroupName()??$group;
        $name = $this->getEventName($eventName);
        if (str_starts_with($name, 'before')) {
            $this->startBenchmarks[$id] = $profiler->convertMicrotime();
            $selector = lcfirst(substr($name, 6));
            $benchmarkSelector = "$group.$selector";

            $meta = $this->getMetadata(
                $eventName,
                $originalParam,
                $param,
                ...$arguments
            );
            $this->recordsBenchmarks[$benchmarkSelector] ??= $profiler->start(
                $benchmarkSelector,
                $groupName,
                $meta
            );
            $this->appendToRecordStatic($eventName, $id);

            return $this;
        } elseif (isset($this->recordsBenchmarks[$eventName])) {
            $benchmark = $this->recordsBenchmarks[$eventName];
            $benchmark->setMetadataRecord(
                'duration',
                $benchmark->convertMicrotime(microtime(true)) - $benchmark->getStartTime()
            );
            $this->appendToRecordStatic($eventName, $id);
        } elseif (str_starts_with($name, 'after')) {
            $selector = lcfirst(substr($name, 5));
            $benchmarkSelector = "$group.$selector";

            if (!isset($this->recordsBenchmarks[$benchmarkSelector])) {
                return null;
            }
            $this->startBenchmarks[$id] = $profiler->convertMicrotime();
            $meta = $this->getMetadata(
                $eventName,
                $originalParam,
                $param,
                ...$arguments
            );
            $benchmark = $this->recordsBenchmarks[$benchmarkSelector];
            if (!$benchmark->hasMetadataRecord('duration')) {
                $benchmark->setMetadataRecord(
                    'duration',
                    $benchmark->convertMicrotime(microtime(true)) - $benchmark->getStartTime()
                );
            }

            $benchmark->stop($meta);
            $benchmark->setMetadataRecord('stopped', true);
            $this->appendToRecordStatic($eventName, $id);

            return $this;
        } else {
            $this->singleBenchmarks[$eventName][$id] = $profiler
                ->start(
                    $eventName,
                    $groupName,
                    $this->getMetadata(
                        $eventName,
                        $originalParam,
                        $param,
                        ...$arguments
                    )
                );
            $this->appendToRecordStatic($eventName, $id);
            return $this;
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function stop(
        ManagerProfiler $managerProfiler,
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        #[SensitiveParameter] $originalParam,
        #[SensitiveParameter] $param,
        #[SensitiveParameter] ...$arguments
    ): void {
        if (isset($this->singleBenchmarks[$eventName][$id])) {
            $benchmark = $this->singleBenchmarks[$eventName][$id];
            if (!$benchmark->hasMetadataRecord('duration')) {
                $benchmark->setMetadataRecord(
                    'duration',
                    $benchmark->convertMicrotime(microtime(true)) - $benchmark->getStartTime()
                );
            }
            $benchmark->stop(['stopped' => true]);
            unset($this->singleBenchmarks[$eventName][$id]);
            return;
        }

        if (!is_float($this->startBenchmarks[$id]??null)) {
            return;
        }

        $group = $this->getEventGroup($eventName);
        $name = $this->getEventName($eventName);
        if (str_starts_with($name, 'before')) {
            $selector = lcfirst(substr($name, 6));
            $benchmarkSelector = "$group.$selector";
            $nameDuration = 'Before';
        } elseif (str_starts_with($name, 'after')) {
            $selector = lcfirst(substr($name, 5));
            $nameDuration = 'After';
            $benchmarkSelector = "$group.$selector";
            $benchmark = $this->recordsBenchmarks[$benchmarkSelector]??null;
            unset($this->recordsBenchmarks[$benchmarkSelector]);
        } else {
            return;
        }

        $benchmark ??= $this->recordsBenchmarks[$benchmarkSelector]??null;
        $benchmark?->setMetadataRecord(
            sprintf('%s Duration', $nameDuration),
            $benchmark->convertMicrotime(microtime(true)) - $this->startBenchmarks[$id]
        );
        unset($this->startBenchmarks[$id]);
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    protected function getMetadata(
        string $eventName,
        #[SensitiveParameter] $originalParam,
        #[SensitiveParameter] $param,
        #[SensitiveParameter] ...$arguments
    ): array {
        return [
            'parameters' => array_merge([$param], $arguments)
        ];
    }

    public function __destruct()
    {
        $this->recordsBenchmarks = [];
    }
}
