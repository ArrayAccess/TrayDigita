<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Traits;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;

trait ProfilingTrait
{
    abstract public function getProfiler() : ProfilerInterface;

    protected function benchmarkStart(
        string $name,
        string $group = ProfilerInterface::DEFAULT_NAME,
        array $context = []
    ): RecordInterface {
        return $this->getProfiler()->start($name, $group, $context);
    }

    protected function benchmarkStop(
        string $name,
        string $group = ProfilerInterface::DEFAULT_NAME,
        ?array $context = null
    ): RecordInterface {
        return $this->getProfiler()->start($name, $group, $context);
    }
}
