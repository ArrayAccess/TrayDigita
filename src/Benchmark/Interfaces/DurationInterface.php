<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

interface DurationInterface
{
    public const NUMERIC_INFO = 1;
    public const NUMERIC_NOTICE = 5;
    public const NUMERIC_WARNING = 10;
    public const NUMERIC_CRITICAL = 20;

    public function getStartTime(): float;

    public function getEndTime(): float;

    public function getDuration() : float;
}
