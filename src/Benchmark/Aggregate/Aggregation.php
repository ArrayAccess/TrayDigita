<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregate;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregationInterface;
use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregatorInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;

class Aggregation implements AggregationInterface
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var array<string, RecordInterface>
     *     Index key as
     * @uses spl_object_hash(\ArrayAccess\TrayDigita\Performance\Interfaces\RecordInterface)
     */
    private array $records;

    /**
     * @var array
     */
    private array $recordsExecutions;

    /**
     * @var int
     */
    private int $totalExecution = 0;

    /**
     * @var float
     */
    private float $totalDuration = 0.0;

    /**
     * @var float
     */
    private float $minimumDuration = 0.0;

    /**
     * @var float
     */
    private float $maximumDuration = 0.0;

    /**
     * @var float
     */
    private float $averageDuration = 0.0;

    /**
     * @param ?string $name
     */
    public function __construct(?string $name = null)
    {
        $this->name    = $name??$this::class;
        $this->records = [];
        $this->recordsExecutions = [];
    }

    public function getName() : string
    {
        return $this->name;
    }

    final public function aggregate(RecordInterface $record, AggregatorInterface $aggregator) : static
    {
        $id = spl_object_hash($record);

        $this->totalExecution++;
        $duration               = $record->getDuration();
        $this->totalDuration   += $duration;
        $this->averageDuration = $this->totalDuration / $this->totalExecution;
        if ($duration > $this->maximumDuration) {
            $this->maximumDuration = $duration;
        }
        if ($duration < $this->minimumDuration || $this->minimumDuration <= 0.0) {
            $this->minimumDuration = $duration;
        }

        $this->recordsExecutions[$id] ??= 0;
        $this->recordsExecutions[$id]++;

        $this->records[$id] = $record;
        $this->afterAggregate($record, $aggregator);
        return $this;
    }

    protected function afterAggregate(RecordInterface $benchmark, AggregatorInterface $aggregator)
    {
        // override
    }

    /**
     * @return int
     */
    public function getTotalExecution() : int
    {
        return $this->totalExecution;
    }

    public function getRecordsExecutions(): array
    {
        return $this->recordsExecutions;
    }

    public function getRecordsExecution(RecordInterface $record): ?int
    {
        return $this->recordsExecutions[spl_object_hash($record)]??null;
    }

    /**
     * @return float
     */
    public function getTotalDuration() : float
    {
        return $this->totalDuration;
    }

    /**
     * @return float
     */
    public function getMinimumDuration() : float
    {
        return $this->minimumDuration;
    }

    /**
     * @return float
     */
    public function getMaximumDuration() : float
    {
        return $this->maximumDuration;
    }

    /**
     * @return float
     */
    public function getAverageDuration() : float
    {
        return $this->averageDuration;
    }

    /**
     * @return array<string, RecordInterface>
     */
    public function getRecords() : array
    {
        return $this->records;
    }

    public function jsonSerialize() : array
    {
        return [
            'name' => $this->getName(),
            'execution' => [
                'total' => $this->getTotalExecution(),
                'records' => $this->getRecordsExecutions(),
            ],
            'duration' => [
                'total'   => $this->getTotalDuration(),
                'minimum' => $this->getMinimumDuration(),
                'maximum' => $this->getMaximumDuration(),
                'average' => $this->getAverageDuration(),
            ],
            // 'records' => $this->getRecords(),
        ];
    }

    public function __toString() : string
    {
        return json_encode($this->jsonSerialize(), JSON_UNESCAPED_SLASHES);
    }
}
