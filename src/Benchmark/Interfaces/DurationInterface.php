<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

interface DurationInterface
{
    const NUMERIC_INFO = 1;
    const NUMERIC_NOTICE = 5;
    const NUMERIC_WARNING = 10;
    const NUMERIC_CRITICAL = 20;

    public function getStartTime(): float;

    public function getEndTime(): float;

    public function getDuration() : float;
}
