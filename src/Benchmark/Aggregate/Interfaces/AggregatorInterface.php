<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\SeverityInterface;

interface AggregatorInterface extends SeverityInterface, AggregateInterface
{
    public function __construct(ProfilerInterface $profiler);

    /**
     * @return ?ProfilerInterface
     */
    public function getProfiler() : ?ProfilerInterface;

    /**
     * @param ProfilerInterface $profiler
     */
    public function setProfiler(ProfilerInterface $profiler);

    /**
     * @return string
     */
    public function getName() : string;

    /**
     * @return string
     */
    public function getGroupName() : string;

    /**
     * @param RecordInterface $record
     *
     * @return bool
     */
    public function accepted(RecordInterface $record) : bool;

    /**
     * @param RecordInterface $record
     *
     * @return bool
     */
    public function aggregate(RecordInterface $record) : bool;

    /**
     * @return array<string, AggregationInterface>
     */
    public function getAggregations() : array;

    /**
     * @param string $identity
     *
     * @return AggregationInterface
     */
    public function getAggregation(string $identity) : AggregationInterface;

    /**
     * @return AggregationInterface
     */
    public function getInternalAggregation() : AggregationInterface;

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
     * @return int
     */
    public function getTotalExecution() : int;

    /**
     * @return array<RecordInterface>
     */
    public function getRecords() : array;
}
