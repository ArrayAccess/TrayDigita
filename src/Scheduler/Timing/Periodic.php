<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Timing;

use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\SchedulerTimeInterface;
use ArrayAccess\TrayDigita\Scheduler\Runner;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use function array_unique;
use function array_values;
use function date;
use function gmdate;
use function min;
use function strtotime;
use function time;

final class Periodic implements SchedulerTimeInterface
{
    protected ?DateTimeInterface $from;

    protected ?DateTimeInterface $until;

    protected array $days = [];

    protected ?int $hour;

    protected ?int $minutes;
    private int $totalDaysCurrentMonth;

    private int $currentDayOfTheMonth;

    public function __construct(
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $until = null,
        ?int $minutes = null,
        ?int $hour = null,
        int ...$days
    ) {
        $this->totalDaysCurrentMonth = (int) date('t');
        $this->currentDayOfTheMonth = (int) date('j');

        if ($from && $until && $from->getTimestamp() > $until->getTimestamp()) {
            $from = null;
        }
        $this->from = $from;
        $this->until = $until;
        if ($hour === null) {
            $hour = $this->from?->format('H')??$this->until?->format('H')??null;
        }

        $this->hour = $hour !== null ? $hour % 24 : null;
        $this->minutes = $minutes !== null ? $minutes % 60 : null;
        $this->setDays(...$days);
    }

    public function getFrom(): ?DateTimeInterface
    {
        return $this->from;
    }

    public function setFrom(?DateTimeInterface $from): void
    {
        $this->from = $from;
    }

    public function getUntil(): ?DateTimeInterface
    {
        return $this->until;
    }

    public function setUntil(?DateTimeInterface $until): void
    {
        $this->until = $until;
    }

    public function getHour(): ?int
    {
        return $this->hour;
    }

    public function setHour(?int $hour): void
    {
        $this->hour = $hour;
    }

    public function getMinutes(): ?int
    {
        return $this->minutes;
    }

    public function setMinutes(?int $minutes): void
    {
        $this->minutes = $minutes;
    }

    public function getDays(): array
    {
        return $this->days;
    }

    public function setDays(int ...$days): void
    {
        if ($days === []) {
            $this->days = [];
            return;
        }
        foreach ($days as $day) {
            $dayNow = $day % $this->totalDaysCurrentMonth;
            $dayNow = $dayNow === 0 ? $this->totalDaysCurrentMonth : $dayNow;
            $this->days[] = $dayNow;
        }
        $this->days = array_values(array_unique($this->days));
    }

    public function shouldSkipTime(): bool
    {
        return $this->hour === null && $this->minutes === null;
    }

    /**
     * @throws Exception
     */
    public function getNextRunDate(?DateTimeZone $timezone = null) : DateTime
    {
        if (!empty($this->days)) {
            $collections = [];
            foreach ($this->days as $day) {
                if ($day > $this->currentDayOfTheMonth) {
                    $collections[] = $day;
                }
            }
            $day = !empty($collections) ? min($collections) : min($this->days);
            if ($day > $this->currentDayOfTheMonth) {
                $daySeconds = ($day - $this->currentDayOfTheMonth) * 86400;
            } else {
                $daySeconds = $this->currentDayOfTheMonth + $this->totalDaysCurrentMonth - $day;
                $daySeconds *= 86400;
            }
        } else {
            $daySeconds = 0;
        }

        $daySeconds += ($hour??0) * 3600;
        $daySeconds += ($minutes??0) * 60;
        $date = new DateTime(timezone: $timezone);
        if ($daySeconds === 0) {
            return $date;
        }
        $date->modify("+ $daySeconds seconds");
        return $date;
    }

    public function shouldRun(Task $task, int $lastExecuteTime, int $lastStatusCode): bool
    {
        if ($this->shouldSkipTime()) {
            return false;
        }

        $time = time();
        $remainingExecutionTime = ($time - $lastExecuteTime);
        // by pass progress
        if ($lastStatusCode === Runner::STATUS_PROGRESS
            && $lastExecuteTime >= Runner::PREVIOUS_MIN_TIME
            && Runner::MAXIMUM_RUNNING_TIME <= $remainingExecutionTime
        ) {
            return true;
        }

        $format = 'Y-m-d H:i:00';
        $from    = strtotime(gmdate($format, $this->from?->getTimestamp()));
        $until   = strtotime(gmdate($format, $this->until?->getTimestamp()));
        $current = strtotime(gmdate($format));
        if ($from > $current || $until < $current) {
            return false;
        }

        $shouldSkip = Runner::shouldSkip($lastStatusCode);
        if (!$shouldSkip
            && $this->hour === null
            && $this->minutes === null
            && $this->days === []
        ) {
            return true;
        }

        $currentHour = (
            $this->hour !== null
                ? (int)gmdate('H') // use hour
                : 0
        ) * 3600 + (
            $this->minutes !== null
                ? (int)gmdate('i')
                : 0
        ) * 60;
        $currentAddition  = (($this->hour??0) * 3600) + ($this->minutes??0) * 60;
        // last is empty
        if ($lastExecuteTime <= 0) {
            $shouldRun = $currentHour <= $currentAddition;
        } else {
            $last = strtotime(gmdate($format, $lastExecuteTime));
            $remaining = $current - $last;
            $shouldRun = $remaining < 0
                || $remaining > $currentAddition
                // if not match check
                || !$shouldSkip;
        }

        if (!$shouldRun) {
            return $lastStatusCode === Runner::STATUS_PROGRESS
                && $task->isForceRunInProgress();
        }

        // if empty days
        if ($this->days === []) {
            return true;
        }
        foreach ($this->days as $day) {
            if ($day === $this->currentDayOfTheMonth) {
                return true;
            }
            if ($day < $this->currentDayOfTheMonth) {
                $remainDaySeconds = ($this->currentDayOfTheMonth - $day) * 24 * 3600;
                if ($remainDaySeconds < $remainingExecutionTime) {
                    return true;
                }
            }
        }

        return $lastStatusCode === Runner::STATUS_PROGRESS
            && $task->isForceRunInProgress();
    }
}
