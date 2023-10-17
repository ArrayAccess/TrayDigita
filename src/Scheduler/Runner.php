<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler;

use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use ArrayAccess\TrayDigita\Scheduler\Messages\Failure;
use Throwable;
use function debug_backtrace;
use function microtime;
use function register_shutdown_function;
use function time;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

final class Runner
{
    final const STATUS_QUEUE = 0;

    final const STATUS_SUCCESS = 1;

    final const STATUS_FAILURE = 2;

    final const STATUS_STOPPED = 3;

    final const STATUS_PROGRESS = 4;

    final const STATUS_UNKNOWN = 5;

    final const STATUS_EXITED = 6;

    final const STATUS_SKIPPED = 7;

    // maximum running time
    final const MAXIMUM_RUNNING_TIME = 3600; // 1 hour

    final const PREVIOUS_MIN_TIME = 1694019600; // 2023-09-07

    final const PERFORM_RESTORE = [
        self::STATUS_SUCCESS,
        self::STATUS_FAILURE,
        self::STATUS_UNKNOWN,
        self::STATUS_SKIPPED
    ];

    private int $status = self::STATUS_QUEUE;

    private ?MessageInterface $message = null;

    private ?float $executeTime = null;

    private ?float $executedTime = null;

    private bool $ended = false;

    public static function shouldSkip(int $status): bool
    {
        return match ($status) {
            self::STATUS_PROGRESS,
            self::STATUS_SUCCESS,
            self::STATUS_EXITED,
            self::STATUS_FAILURE => true,
            default => false
        };
    }

    public function __construct(
        public readonly Scheduler $scheduler,
        private readonly Task $task,
        private LastRecord $lastRecord,
        private int $schedulerTimeStart
    ) {
    }

    public function getSchedulerTimeStart(): int
    {
        return $this->schedulerTimeStart;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getScheduler(): Scheduler
    {
        return $this->scheduler;
    }

    public function getLastRecord(): ?LastRecord
    {
        return $this->lastRecord;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getMessage(): ?MessageInterface
    {
        return $this->message;
    }

    public function getExecuteTime(): ?float
    {
        return $this->executeTime;
    }

    public function getExecutedTime(): ?float
    {
        return $this->executedTime;
    }

    public function getExecutionDuration() : ?float
    {
        $start = $this->getExecuteTime();
        $end = $this->getExecutedTime();
        if ($start === null || $end === null) {
            return null;
        }

        return ($end - $start);
    }

    public function getNormalizeStatus() : int
    {
        return match ($this->status) {
            self::STATUS_QUEUE,
            self::STATUS_FAILURE,
            self::STATUS_STOPPED,
            self::STATUS_SKIPPED,
            self::STATUS_PROGRESS,
            self::STATUS_EXITED,
            self::STATUS_SUCCESS => $this->status,
            default => self::STATUS_UNKNOWN,
        };
    }

    private function createTime(): float
    {
        return (float) (microtime(true) * 1000);
    }

    /** @noinspection DuplicatedCode */
    public function skip() : self
    {
        $time_start = time();
        if ($this->status !== self::STATUS_QUEUE) {
            return $this;
        }
        $traced = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]??[];
        // prevent calling outside
        if (($traced['class']??null) !== $this->getScheduler()::class) {
            return $this;
        }
        $this->schedulerTimeStart = $time_start;
        $this
            ->getScheduler()
            ->getRecordLoader()
            ?->doSkipProgress(
                $this,
                $this->getScheduler(),
            );
        return $this;
    }

    /** @noinspection DuplicatedCode */
    public function process() : self
    {
        $time_start = time();
        if ($this->status !== self::STATUS_QUEUE) {
            return $this;
        }

        $traced = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]??[];
        // prevent calling outside
        if (($traced['class']??null) !== $this->getScheduler()::class) {
            return $this;
        }

        $this->schedulerTimeStart = $time_start;
        $this->lastRecord = $this->lastRecord->withLastExecutionTime(
            $this->schedulerTimeStart
        );

        if (!$this->getScheduler()->shouldRun($this->task)) {
            $this->status = self::STATUS_SKIPPED;
            $this->lastRecord = $this->lastRecord->withStatusCode($this->status);
            return $this;
        }

        register_shutdown_function(function () {
            if (!$this->ended) {
                $this->status = self::STATUS_EXITED;
                $this->lastRecord = $this->lastRecord->withStatusCode($this->status);
            }
        });

        $this->status = self::STATUS_PROGRESS;
        $this->lastRecord = $this
            ->lastRecord
            ->withMessage(null)
            ->withStatusCode($this->status);

        $this
            ->getScheduler()
            ->getRecordLoader()
            ?->doStartProgress(
                $this,
                $this->getScheduler(),
            );
        try {
            $this->executeTime = $this->createTime();
            $message = $this->task->start($this);
            $this->status = self::STATUS_SUCCESS;
            $this->executedTime = $this->createTime();
        } catch (Throwable $e) {
            $this->status = self::STATUS_FAILURE;
            $this->executedTime ??= $this->createTime();
            $message = new Failure($e);
        } finally {
            $this->ended = true;
            $this->lastRecord = $this
                ->lastRecord
                ->withMessage($message);
            return $this;
        }
    }

    public function isPending(): bool
    {
        return $this->getStatusCode() === self::STATUS_QUEUE;
    }

    public function isProgress(): bool
    {
        return $this->getStatusCode() === self::STATUS_PROGRESS;
    }

    public function isSuccess(): bool
    {
        return $this->getStatusCode() === self::STATUS_SUCCESS;
    }

    public function isSkipped(): bool
    {
        return $this->getStatusCode() === self::STATUS_SKIPPED;
    }

    public function isFailure(): bool
    {
        return $this->getStatusCode() === self::STATUS_FAILURE;
    }

    public function isUnknown(): bool
    {
        return $this->getStatusCode() === self::STATUS_UNKNOWN;
    }

    public function isStopped(): bool
    {
        return $this->getStatusCode() === self::STATUS_STOPPED;
    }

    public function isExited(): bool
    {
        return $this->getStatusCode() === self::STATUS_EXITED;
    }
}
