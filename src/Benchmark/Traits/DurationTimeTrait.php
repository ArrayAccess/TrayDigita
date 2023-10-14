<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Traits;

trait DurationTimeTrait
{
    protected float $startTime;

    protected ?float $endTime = null;

    protected ?float $duration = null;

    abstract public function convertMicrotime(?float $microtime = null): float;

    protected function measureTime() : float
    {
        return ($this->getEndTime() - $this->getStartTime());
    }

    /**
     * @return float
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * @return float
     */
    public function getEndTime(): float
    {
        return $this->endTime??$this->convertMicrotime();
    }

    /**
     * @return float
     */
    public function getDuration(): float
    {
        return $this->duration??$this->measureTime();
    }
}
