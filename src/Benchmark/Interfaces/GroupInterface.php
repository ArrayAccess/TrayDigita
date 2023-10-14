<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregateInterface;
use Countable;
use IteratorAggregate;
use JsonSerializable;

interface GroupInterface extends
    AggregateInterface,
    NamingInterface,
    MicrotimeConversionInterface,
    IteratorAggregate,
    Countable,
    JsonSerializable
{
    /**
     * @return ProfilerInterface
     */
    public function getProfiler(): ProfilerInterface;

    public function aggregate(RecordInterface $record) : int;

    public function has(string $name): bool;

    public function get(string $name): ?RecordInterface;

    /**
     * @param string $name
     * @return ?array<string, RecordInterface>
     */
    public function getRecord(string $name) : ?array;

    public function getRecords() : array;

    public function getAllRecords() : array;

    public function start(string $name, array $metadata = []) : RecordInterface;

    public function stop(RecordInterface|string|null $record = null, array $metadata = []): ?RecordInterface;

    public function stopAll(array $metadata = []): array;

    public function getBenchmarksMemoryUsage() : int;

    public function getBenchmarksRealMemoryUsage() : int;
}
