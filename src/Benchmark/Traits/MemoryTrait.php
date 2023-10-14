<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Traits;

trait MemoryTrait
{

    protected int $startMemory;

    protected int $startRealMemory;

    protected ?int $endMemory = null;

    protected ?int $endRealMemory = null;

    protected ?int $usedRealMemory = null;

    protected ?int $usedMemory = null;

    protected function measureMemory(
        int $startMemory,
        bool $real,
        ?int $endMemory = null
    ) : int {
        if ($endMemory === null) {
            $start = memory_get_usage($real);
            $data = $this;
            $end = memory_get_usage($real);
            $endMemory = $end - $start;
        }
        unset($data);
        return max(($endMemory - $startMemory), 0);
    }

    public function getStartMemory(): int
    {
        return $this->startMemory;
    }

    /**
     * @return int
     */
    public function getStartRealMemory(): int
    {
        return $this->startRealMemory;
    }

    public function getEndMemory(): int
    {
        return $this->endMemory??memory_get_usage();
    }

    /**
     * @return int
     */
    public function getEndRealMemory(): int
    {
        return $this->endRealMemory??memory_get_usage(true);
    }

    public function getUsedMemory(): int
    {
        return $this->usedMemory??$this->measureMemory(
            $this->getStartMemory(),
            false
        );
    }

    /**
     * @return int
     */
    public function getUsedRealMemory(): int
    {
        return $this->usedRealMemory??$this->measureMemory(
            $this->getStartRealMemory(),
            true
        );
    }
}
