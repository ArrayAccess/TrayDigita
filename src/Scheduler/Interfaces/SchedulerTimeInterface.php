<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Interfaces;

use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use DateTimeInterface;
use DateTimeZone;

interface SchedulerTimeInterface
{
    public function shouldRun(Task $task, int $lastExecuteTime, int $lastStatusCode): bool;

    public function getNextRunDate(?DateTimeZone $timezone = null) : DateTimeInterface;
}
