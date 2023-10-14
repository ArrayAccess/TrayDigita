<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

interface MemoryInterface
{
    public function getStartMemory(): int;

    public function getEndMemory(): int;

    public function getStartRealMemory(): int;

    public function getEndRealMemory(): int;

    public function getUsedMemory(): int;

    public function getUsedRealMemory(): int;
}
