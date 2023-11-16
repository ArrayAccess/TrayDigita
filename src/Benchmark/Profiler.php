<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark;

use ArrayIterator;
use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregatorInterface;
use ArrayAccess\TrayDigita\Benchmark\Aggregator\DatabaseAggregator;
use ArrayAccess\TrayDigita\Benchmark\Aggregator\EventAggregator;
use ArrayAccess\TrayDigita\Benchmark\Aggregator\KernelAggregator;
use ArrayAccess\TrayDigita\Benchmark\Aggregator\MiddlewareAggregator;
use ArrayAccess\TrayDigita\Benchmark\Aggregator\ServicesAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\GroupInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Benchmark\Traits\DurationTimeTrait;
use ArrayAccess\TrayDigita\Benchmark\Traits\MemoryTrait;
use ReflectionClass;
use ReflectionException;
use SplObjectStorage;
use Traversable;
use function is_string;
use function is_subclass_of;
use function iterator_to_array;
use function max;
use function memory_get_usage;
use function microtime;

class Profiler implements ProfilerInterface
{
    use DurationTimeTrait,
        MemoryTrait;

    private ?ReflectionClass $groupReflection = null;

    /**
     * @var array<string, GroupInterface>
     */
    protected array $groups = [];

    /**
     * @var SplObjectStorage<AggregatorInterface>
     */
    private SplObjectStorage $aggregators;

    public const PROVIDERS = [
        DatabaseAggregator::class,
        KernelAggregator::class,
        MiddlewareAggregator::class,
        ServicesAggregator::class,
        EventAggregator::class,
    ];

    /**
     * @var bool
     */
    private bool $enableProfiler = true;

    public function __construct()
    {
        $this->startTime    = $this->convertMicrotime();
        $this->startMemory  = memory_get_usage();
        $this->startRealMemory = memory_get_usage(true);
        $this->aggregators  = new SplObjectStorage();
        $this->registerAggregatorProviders();
    }

    public function disable(): void
    {
        $this->enableProfiler = false;
    }

    public function enable(): void
    {
        $this->enableProfiler = true;
    }

    public function isEnable(): bool
    {
        return $this->enableProfiler;
    }

    public function registerAggregatorProviders(): void
    {
        $registeredAggregator = [];
        foreach ($this->aggregators as $aggregator) {
            $registeredAggregator[$aggregator::class] = true;
        }
        foreach (static::PROVIDERS as $provider) {
            if (!is_string($provider) || !is_subclass_of($provider, AggregatorInterface::class)) {
                continue;
            }
            if (isset($registeredAggregator[$provider])) {
                continue;
            }
            $aggregator = new $provider($this);
            if (isset($registeredAggregator[$aggregator::class])) {
                continue;
            }
            $this->addAggregator($aggregator);
        }
    }

    public function addAggregator(AggregatorInterface $aggregator): void
    {
        $this->aggregators->attach($aggregator);
    }

    public function removeAggregator(AggregatorInterface $aggregator): void
    {
        $this->aggregators->detach($aggregator);
    }

    public function hasAggregator(AggregatorInterface $aggregator): bool
    {
        return $this->aggregators->contains($aggregator);
    }

    /**
     * @return list<AggregatorInterface>
     */
    public function getAggregators(): iterable
    {
        return iterator_to_array($this->aggregators);
    }

    /**
     * @param RecordInterface $record
     *
     * @return int
     */
    public function aggregate(RecordInterface $record) : int
    {
        $aggregated = 0;
        foreach ($this->getAggregators() as $aggregator) {
            $aggregated += $aggregator->aggregate($record) ? 1 : 0;
        }

        return $aggregated;
    }

    public function convertMicrotime(?float $microtime = null): float
    {
        return (float) (($microtime ?? microtime(true)) * self::EXPONENT);
    }

    public function hasGroup(string $name): bool
    {
        return isset($this->groups[$name]);
    }

    public function addGroup(GroupInterface $group): void
    {
        if (!isset($this->groups[$group->getName()])
            && count($group) > 0
        ) {
            $this->groups[$group->getName()] = $group;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->groups[$id]);
    }

    /**
     * @param string $id
     * @return ?GroupInterface
     */
    public function getGroup(string $id): ?GroupInterface
    {
        return $this->groups[$id]??null;
    }

    public function group(string $id) : GroupInterface
    {
        if (!isset($this->groups[$id])) {
            $this->groupReflection ??= new ReflectionClass(Group::class);
            try {
                $this->groups[$id] = (function ($obj) use ($id) {
                    /**
                     * @var Group $this
                     */
                    $this->{'__construct'}($obj, $id);
                    return $this;
                })->call($this->groupReflection->newInstanceWithoutConstructor(), $this);
            } catch (ReflectionException) {
            }
        }

        return $this->groups[$id];
    }

    /**
     * @return array<string, GroupInterface>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @inheritdoc
     */
    public function getAllRecords(): array
    {
        $records = [];
        foreach ($this->getGroups() as $group) {
            foreach ($group->getAllRecords() as $id => $record) {
                $records[$id] = $record;
            }
        }

        return $records;
    }

    /**
     * @param string $name
     * @param string $group
     * @param array $metadata
     * @return RecordInterface
     */
    public function start(
        string $name,
        string $group = self::DEFAULT_NAME,
        array $metadata = []
    ): RecordInterface {
        return $this->group($group)->start($name, $metadata);
    }

    public function stop(
        string|RecordInterface|null $name = null,
        string $group = self::DEFAULT_NAME,
        array $metadata = []
    ): ?RecordInterface {
        return $this->getGroup($group)?->stop($name, $metadata);
    }

    public function count(): int
    {
        return count($this->groups);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getGroups());
    }

    public function clear(): void
    {
        $this->groups = [];
    }

    /**
     * @return array{
     *     timing: array{
     *         start: float,
     *         end: float,
     *         duration: float
     *     },
     *     memory: array{
     *         normal:array{
     *             start: int,
     *             end: int,
     *             used: int,
     *         },
     *         real:array{
     *              start: int,
     *              end: int,
     *              used: int,
     *         }
     *     },
     *     groups: array<string, GroupInterface>,
     * }
     */
    public function jsonSerialize(): array
    {
        $startTime       = $this->getStartTime();
        $duration        = $this->getDuration();
        $startMemory     = $this->getStartMemory();
        $usedMemory      = $this->getUsedMemory();
        $startRealMemory =  $this->getStartRealMemory();
        $usedRealMemory  = $this->getUsedRealMemory();
        return [
            'timing' => [
                'start' => $startTime,
                'end' => max($duration - $startTime, 0),
                'duration' => $duration
            ],
            'memory' => [
                'normal' => [
                    'start' => $startMemory,
                    'end' => max($usedMemory - $startMemory, 0),
                    'used' => $usedMemory
                ],
                'real' => [
                    'start' => $startRealMemory,
                    'end' => max($usedRealMemory - $startRealMemory, 0),
                    'used' => $usedRealMemory
                ],
            ],
            'aggregators' => $this->getAggregators(),
            'groups' => $this->getGroups(),
        ];
    }
}
