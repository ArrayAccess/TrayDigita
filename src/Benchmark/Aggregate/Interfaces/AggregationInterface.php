<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use JsonSerializable;

interface AggregationInterface extends JsonSerializable
{
    /**
     * @return string aggregation name
     */
    public function getName() : string;

    /**
     * Aggregate the record
     *
     * @param RecordInterface $record
     * @param AggregatorInterface $aggregator
     *
     * @return AggregationInterface
     */
    public function aggregate(RecordInterface $record, AggregatorInterface $aggregator) : static;

    /**
     * @return int
     */
    public function getTotalExecution() : int;

    /**
     * Key as @uses spl_object_hash(RecordInterFace);
     * @return array<string, int>
     */
    public function getRecordsExecutions(): array;

    public function getRecordsExecution(RecordInterface $record): ?int;

    /**
     * @return float
     */
    public function getTotalDuration() : float;

    /**
     * @return float
     */
    public function getMinimumDuration() : float;

    /**
     * @return float
     */
    public function getMaximumDuration() : float;

    /**
     * @return float
     */
    public function getAverageDuration() : float;

    /**
     * @return array<string, RecordInterface>
     */
    public function getRecords() : array;
}
