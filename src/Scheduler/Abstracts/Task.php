<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Abstracts;

use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\SchedulerTimeInterface;
use ArrayAccess\TrayDigita\Scheduler\Messages\Failure;
use ArrayAccess\TrayDigita\Scheduler\Messages\Skipped;
use ArrayAccess\TrayDigita\Scheduler\Messages\Success;
use ArrayAccess\TrayDigita\Scheduler\Messages\Unknown;
use ArrayAccess\TrayDigita\Scheduler\Runner;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use function strtolower;

abstract class Task
{
    /**
     * Minimum interval
     * @final
     */
    final public const MINIMUM_INTERVAL_TIME = 5;

    /**
     * Name of scheduler.
     * @var string $name
     */
    protected string $name = '';

    /**
     * Unique scheduler identity.
     * Should use lowercase alphanumeric characters & underscore only
     * @see getIdentity()
     * @var string $identity
     */
    protected string $identity = '';

    /**
     * This property for default interval if getInterval method is default from task
     * @see getInterval()
     * @var int|SchedulerTimeInterface $interval
     */
    protected int|SchedulerTimeInterface $interval = 0;

    /**
     * @var bool
     */
    protected bool $forceRunInProgress = false;

    final public function __construct(
        public readonly Scheduler $scheduler
    ) {
        $this->name = $this->name?:$this::class;
        $this->identity = $this->identity?:strtolower($this::class);
    }

    /**
     * Scheduler unique identity
     *
     * @return string
     */
    public function getIdentity(): string
    {
        return $this->identity;
    }

    public function isForceRunInProgress(): bool
    {
        return $this->forceRunInProgress;
    }

    public function setForceRunInProgress(bool $forceRunInProgress): void
    {
        $this->forceRunInProgress = $forceRunInProgress;
    }

    /**
     * @return Scheduler
     */
    public function getScheduler(): Scheduler
    {
        return $this->scheduler;
    }

    /**
     * Scheduler name method
     * @see $name
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Scheduler periodic
     * @return int|SchedulerTimeInterface
     */
    public function getInterval(): int|SchedulerTimeInterface
    {
        return $this->interval;
    }

    /**
     * Method to trigger scheduler process
     * Returning status / message can be :
     * @use Success
     * @use Skipped mean the process skipped
     * @use Failure mean the process is failure
     * @use Unknown -> do not use this
     * @param Runner $runner
     * @return MessageInterface
     */
    abstract public function start(Runner $runner): MessageInterface;
}
