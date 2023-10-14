<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark;

use ArrayIterator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ClearableInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\GroupInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Exceptions\Runtime\CloneAbleException;
use Traversable;

class Group implements GroupInterface, ClearableInterface
{
    /**
     * @var array<string, array<string,RecordInterface>>
     */
    private array $records = [];

    /**
     * @var array<string,string>
     */
    private array $queue = [];

    /**
     * @param ProfilerInterface $profiler
     * @param string $name
     */
    final private function __construct(
        private readonly ProfilerInterface $profiler,
        public readonly string $name
    ) {
        $this->profiler->addGroup($this);
    }

    /**
     * @return ProfilerInterface
     */
    public function getProfiler(): ProfilerInterface
    {
        return $this->profiler;
    }

    public function aggregate(RecordInterface $record) : int
    {
        return $this->getProfiler()->aggregate($record);
    }

    public function convertMicrotime(?float $microtime = null): float
    {
        return $this->profiler->convertMicrotime($microtime);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function start(string $name, array $metadata = []) : RecordInterface
    {
        $record = new Record(group: $this, name: $name, metadata: $metadata);
        if (!$this->getProfiler()->isEnable()) {
            return $record;
        }
        $this->records[$name] ??= [];
        $id = spl_object_hash($record);
        $this->records[$name][$id] = $record;
        $this->queue[$id] = $name;
        return $record;
    }

    public function has(string $name): bool
    {
        return isset($this->records[$name]);
    }

    /**
     * @param GroupInterface $group
     * @return void
     */
    public function mergeGroup(GroupInterface $group): void
    {
        // if disable, skip
        if (!$this->getProfiler()->isEnable()) {
            return;
        }
        if ($group === $this) {
            return;
        }

        $groupInstance = $this;
        foreach ($group->getRecords() as $name => $records) {
            foreach ($records as $record) {
                $record = (function () use ($groupInstance) {
                    /**
                     * @var RecordInterface $this
                     */
                    $this->{'group'} = $groupInstance;
                    return $this;
                })->call(clone $record);
                $this->records[$name][spl_object_hash($record)] = $record;
            }
            $this->records = $this->sort(...$this->records[$name]);
        }
    }

    /**
     * @param RecordInterface ...$records
     * @return RecordInterface[]
     */
    public function sort(RecordInterface ...$records): array
    {
        uasort(
            $records,
            static function (RecordInterface $a, RecordInterface $b) {
                $a = $a->getStartTime();
                $b = $b->getStartTime();
                return $a === $b ? 0 : ($a < $b ? -1 : 1);
            }
        );
        return $records;
    }

    /**
     * @param string $name
     * @return ?Record
     */
    public function get(string $name): ?RecordInterface
    {
        $bench = $this->getRecord($name)??[];
        return end($bench)?:null;
    }

    public function getRecord(string $name) : ?array
    {
        return $this->records[$name]??null;
    }

    /**
     * @param ?Record|string $record
     * @param array $metadata
     * @return ?Record
     */
    public function stop(RecordInterface|string|null $record = null, array $metadata = []): ?RecordInterface
    {
        $id = null;
        if ($record === null) {
            $id   = array_key_last($this->queue);
            if ($id === null) {
                return null;
            }
            $name = $this->queue[$id]??null;
            if ($name === null) {
                return null;
            }
            unset($this->queue[$id]);
            $record = $this->records[$name][$id];
        } elseif (is_string($record)) {
            $name = null;
            if (!empty($this->queue)) {
                foreach (array_reverse($this->queue) as $_id => $named) {
                    if ($named === $record) {
                        $id = $_id;
                        $name = $named;
                        break;
                    }
                }
            }
            if ($name === null) {
                if (empty($this->records[$record])) {
                    return null;
                }

                $first = null;
                $id = null;
                foreach (array_reverse($this->records[$record]) as $_id => $record) {
                    if (!$first) {
                        $id = $_id;
                        $first = $record;
                    }
                    if (!$record->isStopped()) {
                        $id = $_id;
                        $first = $record;
                        break;
                    }
                }
                $record = $first;
            } else {
                $record = $this->records[$name][$id]??null;
            }
        } else {
            $name = $record->getName();
            $id = spl_object_hash($record);
            $this->records[$name][$id] = $record;
        }

        if ($id !== null) {
            if ($record && isset($this->queue[$id])) {
                $this->aggregate($record);
            }
            unset($this->queue[$id]);
        }

        return $record?->stop($metadata);
    }

    public function stopAll(array $metadata = []): array
    {
        $stopped = [];
        foreach ($this->queue as $id => $name) {
            $record = $this->records[$name][$id]??null;
            if (!$record) {
                continue;
            }
            $stopped[$id] = $this->stop($record);
        }

        return $stopped;
    }

    public function getBenchmarksMemoryUsage() : int
    {
        $total = 0;
        foreach ($this->getAllRecords() as $record) {
            $total += $record->getUsedMemory();
        }
        return $total;
    }

    public function getBenchmarksRealMemoryUsage() : int
    {
        $total = 0;
        foreach ($this->getAllRecords() as $record) {
            $total += $record->getUsedRealMemory();
        }
        return $total;
    }

    /**
     * @return array<string, array<string, RecordInterface[]>>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @return array<RecordInterface>
     */
    public function getAllRecords() : array
    {
        $records = [];
        foreach ($this->getRecords() as $recordArray) {
            foreach ($recordArray as $id => $record) {
                $records[$id] = $record;
            }
        }
        return $records;
    }

    /**
     * @return array
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    public function clear(): void
    {
        $this->records = [];
        $this->queue = [];
    }

    /**
     * @return Traversable<string, array<string, RecordInterface[]>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getRecords());
    }

    public function count(): int
    {
        return count($this->getRecords());
    }

    /**
     * @return array{
     *     name: string,
     *     records: array<string, array<string, RecordInterface>>,
     * }
     */
    public function jsonSerialize() : array
    {
        return [
            'name' => $this->getName(),
            'records' => $this->getRecords()
        ];
    }

    public function __clone(): void
    {
        throw new CloneAbleException(
            sprintf(
                'Class %s can not being clone.',
                __CLASS__
            ),
            E_USER_ERROR
        );
    }
}
