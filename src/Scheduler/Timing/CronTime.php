<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Timing;

use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\SchedulerTimeInterface;
use ArrayAccess\TrayDigita\Scheduler\Runner;
use Cron\CronExpression;
use DateTimeInterface;
use DateTimeZone;
use function array_slice;
use function explode;
use function implode;
use function max;
use function preg_replace;
use function str_starts_with;
use function time;
use function trim;

class CronTime implements SchedulerTimeInterface
{
    protected CronExpression $cronExpression;

    public function __construct(string $cron)
    {
        $this->cronExpression = new CronExpression(
            CronExpression::supportsAlias($cron)
                ? $cron
                : $this->normalizeCronString($cron)
        );
    }

    public static function create(string $cron): static
    {
        return new static($cron);
    }

    protected function normalizeCronString(string $cron): string
    {
        $cron = trim($cron);
        preg_replace('~\s+~', ' ', $cron);
        $cron = array_slice(explode(' ', $cron, 6), 0, 5);
        while (count($cron) < 5) {
            $cron[] = '*';
        }
        foreach ($cron as $k => $item) {
            if ($item === '*') {
                continue;
            }
            if (str_starts_with($item, '/')) {
                $cron[$k] = "*/$item";
                continue;
            }
            $item = ltrim($item, '-,/');
            if ($item === '') {
                $item = '*';
            }
            $cron[$k] = $item;
        }
        return implode(' ', $cron);
    }

    public function getCronExpression(): CronExpression
    {
        return $this->cronExpression;
    }

    public function getNextRunDate(?DateTimeZone $timezone = null) : DateTimeInterface
    {
        return $this->getCronExpression()->getNextRunDate($timezone);
    }

    public function shouldRun(Task $task, int $lastExecuteTime, int $lastStatusCode): bool
    {
        $inProgress = $lastStatusCode === Runner::STATUS_PROGRESS;
        $shouldSkip = Runner::shouldSkip($lastStatusCode);
        $isDue = $this->getCronExpression()->isDue();
        $time = time();
        $previousTimestamp    = $this->getCronExpression()->getPreviousRunDate()->getTimestamp();
        $nextDueDateTimestamp = $this->getCronExpression()->getNextRunDate()->getTimestamp();
        $interval = ($nextDueDateTimestamp - $previousTimestamp) /2;
        $lastTime = $time - $lastExecuteTime;
        if (!$shouldSkip) {
            $interval = max($interval, Task::MINIMUM_INTERVAL_TIME);
            return ($lastExecuteTime + $interval) < $time;
        }

        if (!$inProgress) {
            return $isDue || $interval <= $lastTime;
        }

        if ($lastExecuteTime >= Runner::PREVIOUS_MIN_TIME
            && Runner::MAXIMUM_RUNNING_TIME <= $lastTime
        ) {
            return true;
        }

        return $lastStatusCode === Runner::STATUS_PROGRESS
            && $task->isForceRunInProgress();
    }
}
