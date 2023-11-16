<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregateInterface;
use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregatorInterface;
use Countable;
use IteratorAggregate;
use JsonSerializable;

interface ProfilerInterface extends
    MemoryInterface,
    DurationInterface,
    MicrotimeConversionInterface,
    AggregateInterface,
    IteratorAggregate,
    Countable,
    ClearableInterface,
    JsonSerializable
{
    public const DEFAULT_NAME = 'default';

    /**
     * Disable profiler record
     */
    public function disable();

    /**
     * Enable profiler record
     */
    public function enable();

    /**
     * @return bool
     */
    public function isEnable() : bool;

    /**
     * Aggregate the record
     *
     * @param RecordInterface $record
     * @return int
     */
    public function aggregate(RecordInterface $record) : int;

    /**
     * Get group if exists
     *
     * @param string $id
     * @return GroupInterface|null
     */
    public function getGroup(string $id): ?GroupInterface;

    /**
     * Get or create new group
     *
     * @param string $id
     * @return GroupInterface
     */
    public function group(string $id) : GroupInterface;

    /**
     * Get group list
     *
     * @return array<string, GroupInterface>
     */
    public function getGroups(): array;

    /**
     * Get all records in groups
     *
     * @return array<string, RecordInterface>
     */
    public function getAllRecords(): array;

    /**
     * Start record
     *
     * @param string $name
     * @param string $group
     * @param array $metadata
     * @return RecordInterface
     */
    public function start(
        string $name,
        string $group = self::DEFAULT_NAME,
        array $metadata = []
    ): RecordInterface;

    /**
     * Stop the record
     *
     * @param string|RecordInterface|null $name
     * @param string $group
     * @param array $metadata
     * @return RecordInterface|null
     */
    public function stop(
        string|RecordInterface|null $name = null,
        string $group = self::DEFAULT_NAME,
        array $metadata = []
    ): ?RecordInterface;

    /**
     * Append aggregators
     *
     * @param AggregatorInterface $aggregator
     */
    public function addAggregator(AggregatorInterface $aggregator);

    /**
     * Remove aggregator
     *
     * @param AggregatorInterface $aggregator
     */
    public function removeAggregator(AggregatorInterface $aggregator);

    /**
     * Check if it has aggregator
     *
     * @param AggregatorInterface $aggregator
     * @return bool
     */
    public function hasAggregator(AggregatorInterface $aggregator): bool;

    /**
     * @return iterable<AggregatorInterface>
     */
    public function getAggregators(): iterable;
}
