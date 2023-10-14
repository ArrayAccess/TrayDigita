<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregate;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregationInterface;
use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregatorInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\DurationInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\SeverityInterface;
use Countable;
use JsonSerializable;

abstract class AbstractAggregator implements
    SeverityInterface,
    AggregatorInterface,
    Countable,
    JsonSerializable
{
    /**
     * @var string
     */
    protected string $name = '';

    /**
     * @var string
     */
    protected string $groupName = '';

    /**
     * @var array<string, AggregationInterface>
     */
    protected array $aggregations = [];

    /**
     * @var ?AggregationInterface
     */
    protected ?AggregationInterface $internalAggregation = null;

    /**
     * @var int
     */
    protected int $countedAsNotice = DurationInterface::NUMERIC_NOTICE;

    /**
     * @var int
     */
    protected int $countedAsWarning = DurationInterface::NUMERIC_WARNING;

    /**
     * @var int
     */
    protected int $countedAsCritical = DurationInterface::NUMERIC_CRITICAL;

    /**
     * @var int
     */
    protected int $durationAsNotice = DurationInterface::NUMERIC_NOTICE;

    /**
     * @var int
     */
    protected int $durationAsWarning = DurationInterface::NUMERIC_WARNING;

    /**
     * @var int
     */
    protected int $durationAsCritical = DurationInterface::NUMERIC_CRITICAL;

    protected ProfilerInterface $profiler;

    /**
     * @param ProfilerInterface $collector
     */
    final public function __construct(ProfilerInterface $collector)
    {
        $this->setProfiler($collector);
        $this->onConstruct();
    }

    public function getProfiler(): ProfilerInterface
    {
        return $this->profiler;
    }

    public function setProfiler(ProfilerInterface $profiler): void
    {
        $this->profiler = $profiler;
    }

    protected function onConstruct()
    {
        // override
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getGroupName() : string
    {
        return $this->groupName;
    }

    /**
     * @param RecordInterface $record
     *
     * @return bool
     */
    public function aggregate(RecordInterface $record) : bool
    {
        if (true !== $this->accepted($record)) {
            return false;
        }

        $this
            ->getInternalAggregation()
            ->aggregate($record, $this);
        $this
            ->getAggregation($this->getIdentity($record))
            ->aggregate($record, $this);

        return true;
    }

    /**
     * @param RecordInterface $record
     *
     * @return string
     */
    public function getIdentity(RecordInterface $record) : string
    {
        return $record->getGroup()->getName();
    }

    /**
     * @param RecordInterface $record
     *
     * @return bool
     */
    public function accepted(RecordInterface $record) : bool
    {
        return $this->getIdentity($record) === $this->getGroupName();
    }

    /**
     * @return array<string, AggregationInterface>
     */
    public function getAggregations() : array
    {
        return $this->aggregations;
    }

    public function getAggregation(string $identity) : AggregationInterface
    {
        return $this->aggregations[$identity] ??= new Aggregation($identity);
    }

    /**
     * @return AggregationInterface
     */
    public function getInternalAggregation() : AggregationInterface
    {
        return $this->internalAggregation ??= new InternalAggregation();
    }

    public function getTotalDuration() : float
    {
        return $this->getInternalAggregation()->getTotalDuration();
    }

    public function getMinimumDuration() : float
    {
        return $this->getInternalAggregation()->getMinimumDuration();
    }

    public function getMaximumDuration() : float
    {
        return $this->getInternalAggregation()->getMaximumDuration();
    }

    public function getAverageDuration() : float
    {
        return $this->getInternalAggregation()->getAverageDuration();
    }

    public function getTotalExecution() : int
    {
        return $this->getInternalAggregation()->getTotalExecution();
    }

    public function isCritical() : bool
    {
        return $this->getTotalExecution() >= $this->countedAsCritical
            || $this->getTotalDuration() >= $this->durationAsCritical;
    }

    public function isWarning() : bool
    {
        return $this->getTotalExecution() >= $this->countedAsWarning
            || $this->getTotalDuration() >= $this->durationAsWarning;
    }

    public function isNotice() : bool
    {
        return $this->getTotalExecution() >= $this->countedAsNotice
            || $this->getTotalDuration() >= $this->durationAsNotice;
    }

    public function isInfo() : bool
    {
        return $this->count() === 0;
    }

    public function getSeverity() : int
    {
        if ($this->isInfo()) {
            return self::INFO;
        }
        if ($this->isCritical()) {
            return self::CRITICAL;
        }
        if ($this->isWarning()) {
            return self::WARNING;
        }
        if ($this->isNotice()) {
            return self::NOTICE;
        }
        return self::INFO;
    }

    public function getRecords() : array
    {
        return $this->getInternalAggregation()->getRecords();
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return $this->getTotalExecution();
    }

    public function jsonSerialize() : array
    {
        return [
            'name' => $this->getName(),
            'severity' => $this->getSeverity(),
            'execution' => [
                'total' => $this->getTotalExecution(),
            ],
            'duration' => [
                'total' => $this->getTotalDuration(),
                'minimum' => $this->getMinimumDuration(),
                'maximum' => $this->getMaximumDuration(),
                'average' => $this->getAverageDuration(),
            ],
            'aggregations' => $this->getAggregations()
        ];
    }

    public function __toString() : string
    {
        return json_encode($this->jsonSerialize(), JSON_UNESCAPED_SLASHES);
    }
}
