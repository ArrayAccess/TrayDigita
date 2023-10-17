<?php
declare(strict_types=1, ticks=1);

namespace ArrayAccess\TrayDigita\Scheduler;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\RecordLoaderInterface;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\SchedulerTimeInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionUnionType;
use Throwable;
use function array_shift;
use function call_user_func;
use function count;
use function date;
use function func_get_args;
use function is_a;
use function is_array;
use function is_int;
use function max;
use function register_shutdown_function;
use function reset;
use function spl_object_hash;
use function sprintf;
use function time;
use function uasort;

/**
 * Please does not change anything from this file when ready for production
 * it will make change anonymous class name
 */
class Scheduler implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait {
        setContainer as private setContainerTrait;
    }
    use ManagerAllocatorTrait,
        TranslatorTrait;

    /**
     * @var array<Task>
     */
    private array $queue = [];

    /**
     * @var array<string, Task>
     */
    private array $progress = [];

    /**
     * @var array<string, Runner>
     */
    private array $finished = [];

    /**
     * @var array<string, Task>
     */
    private array $skipped = [];

    /**
     * @var ?array<string, Task>
     */
    private ?array $shouldRunning = null;

    private ?RecordLoaderInterface $recordLoader = null;

    public function __construct(
        ?ContainerInterface $container = null,
        ?ManagerInterface $manager = null
    ) {
        if ($container) {
            $this->setContainer($container);
            if (!$manager && $container->has(ManagerInterface::class)) {
                try {
                    $manager = $container->get(ManagerInterface::class);
                } catch (Throwable) {
                }

                if (!$manager instanceof ManagerInterface) {
                    $manager = null;
                }
            }
        }
        if ($manager) {
            $this->setManager($manager);
        }
    }

    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        if ($this->recordLoader instanceof ContainerAllocatorInterface) {
            $this->recordLoader->setContainer($container);
        }
        return $this->setContainerTrait($container);
    }

    public function getRecordLoader(): RecordLoaderInterface
    {
        if (!$this->recordLoader) {
            $this->recordLoader = new LocalRecordLoader(
                $this->getContainer()
            );
        }
        return $this->recordLoader;
    }

    public function setRecordLoader(?RecordLoaderInterface $recordLoader): void
    {
        $this->recordLoader = $recordLoader;
    }

    /**
     * @param string $name
     * @param callable $callback
     * @param int|SchedulerTimeInterface $interval
     * @param ?int $lastExecutionTime
     * @param ?int $previousCode
     * @return Task
     * @throws UnsupportedArgumentException
     * @noinspection PhpVariableIsUsedOnlyInClosureInspection
     */
    public function createTask(
        string $name,
        callable $callback,
        int|SchedulerTimeInterface $interval,
        ?int $lastExecutionTime = null,
        ?int $previousCode = null
    ) : Task {
        $found = false;
        try {
            $ref = is_array($callback)
                ? new ReflectionMethod(reset($callback), end($callback)??null)
                : new ReflectionFunction($callback);
            $returnType = $ref->getReturnType();
            $returnType = $returnType instanceof ReflectionUnionType
                ? $returnType->getTypes()
                : ($returnType ? [$returnType] : []);
            foreach ($returnType as $type) {
                if ($type->isBuiltin()) {
                    continue;
                }
                if (is_a($type->getName(), MessageInterface::class)) {
                    $found = true;
                    break;
                }
            }
        } catch (Throwable) {
        }
        if (!$found) {
            throw new UnsupportedArgumentException(
                sprintf(
                    $this->translateContext(
                        'Callback task must be contain return type or instance of: %s',
                        'scheduler'
                    ),
                    MessageInterface::class
                )
            );
        }
        $object = new class($this) extends Task {

            /**
             * @noinspection PhpMissingFieldTypeInspection
             * @noinspection PhpPropertyOnlyWrittenInspection
             */
            private $callable;

            public function start(Runner $runner): MessageInterface
            {
                return call_user_func($this->callable, $runner, $this);
            }
        };
        $obj = $this;
        return (function (
            $name,
            $callback,
            $interval,
            $lastExecutionTime,
            $previousCode
        ) use ($obj) : Task {

            $hasPrevious = $previousCode !== null
                && $previousCode !== Runner::STATUS_UNKNOWN
                && $lastExecutionTime !== null
                && $lastExecutionTime > Runner::PREVIOUS_MIN_TIME;

            /**
             * @var Task $this
             */
            $task = $this;
            $this->{'name'} = $name;
            $this->{'callable'} = $callback;
            $this->{'interval'} = $interval;
            $this->{'previousCode'} = $previousCode??Runner::STATUS_UNKNOWN;
            $this->{'lastExecutionTime'} = null;
            $previous = !$hasPrevious
                ? $obj->getRecordLoader()->getRecord($task)
                : null;
            if ($previous !== null
                && $previous->getStatusCode() !== Runner::STATUS_UNKNOWN
            ) {
                $this->{'previousCode'} = $previous->getStatusCode();
            }
            return $this;
        })->call(
            $object,
            $name,
            $callback,
            $interval,
            $lastExecutionTime,
            $previousCode
        );
    }

    public function add(Task $scheduler): void
    {
        // reset on add task
        $id = spl_object_hash($scheduler);
        if (!isset($this->queue[$id])) {
            $this->shouldRunning = null;
        }
        $this->queue[$id] = $scheduler;
    }

    /**
     * @param string $name
     * @param callable $callback
     * @param int|SchedulerTimeInterface $interval
     * @param int|null $lastExecutionTime
     * @param int|null $previousCode
     * @return Task
     * @see createTask()
     */
    public function addCallable(
        string $name,
        callable $callback,
        int|SchedulerTimeInterface $interval,
        ?int $lastExecutionTime = null,
        ?int $previousCode = null
    ): Task {
        $task = $this->createTask(...func_get_args());
        $this->add($task);
        return $task;
    }

    /**
     * @param Task $task
     * @return ?DateTimeInterface
     */
    public function getNextRunDate(Task $task): ?DateTimeInterface
    {
        $interval = $task->getInterval();
        if ($interval instanceof SchedulerTimeInterface) {
            return $interval->getNextRunDate();
        }
        if ($interval === 0) {
            return null;
        }
        $time = time();
        $record = $this->getRecordLoader()->getRecord($task);
        $interval = max($interval, Task::MINIMUM_INTERVAL_TIME);
        $last_time = $record?->getLastExecutionTime()??0;
        $last  = $time - $last_time;
        $nextRun = $last_time + $interval;
        $next  = $time - $nextRun;
        if ($last_time === 0 || $last < 0 || $next > 0 || Runner::MAXIMUM_RUNNING_TIME <= $last_time) {
            try {
                // if not valid add current time + interval
                return new DateTimeImmutable(date('c', $time + $interval));
            } catch (Exception) {
                return (new DateTimeImmutable())->modify("+$interval seconds");
            }
        }
        try {
            return new DateTimeImmutable(date('c', $nextRun));
        } catch (Exception) {
            $interval = $time - $nextRun;
            return (new DateTimeImmutable())->modify("+$interval seconds");
        }
    }

    public function shouldRun(Task $task) : bool
    {
        $interval = $task->getInterval();
        // zero should skipped
        if ($interval === 0) {
            return false;
        }

        if ($this->shouldRunning !== null && isset($this->shouldRunning[spl_object_hash($task)])) {
            return true;
        }

        $record = $this->getRecordLoader()->getRecord($task);
        $last_time = $record?->getLastExecutionTime()??0;
        $last_status_code = $record?->getStatusCode()??Runner::STATUS_UNKNOWN;
        $time = time();

        // by pass progress
        if ($last_status_code === Runner::STATUS_PROGRESS
            && $last_time >= Runner::PREVIOUS_MIN_TIME
            && Runner::MAXIMUM_RUNNING_TIME <= ($time - $last_time)
        ) {
            return true;
        }

        if (is_int($interval)) {
            $interval = max($interval, Task::MINIMUM_INTERVAL_TIME);
            $shouldRun = ($last_time + $interval) < $time;
            return $shouldRun || !Runner::shouldSkip($last_status_code);
        }

        return $interval->shouldRun($task, $last_time, $last_status_code);
    }

    public function getProgressCount() : int
    {
        return count($this->progress);
    }

    public function getQueueCount() : int
    {
        return count($this->queue);
    }

    public function getFinishCount() : int
    {
        return count($this->finished);
    }

    public function isInQueue(Task $task) : bool
    {
        return isset($this->queue[spl_object_hash($task)]);
    }

    public function getQueue(): array
    {
        return $this->queue;
    }

    public function getProgress(): array
    {
        return $this->progress;
    }

    /**
     * @return Runner[]
     */
    public function getFinished(): array
    {
        return $this->finished;
    }

    public function getSkipped(): array
    {
        return $this->skipped;
    }

    public function isFinish(Task $task) : bool
    {
        return isset($this->finished[spl_object_hash($task)]);
    }

    public function isInProgress(Task $task) : bool
    {
        return isset($this->progress[spl_object_hash($task)]);
    }

    public function isSkipped(Task $task) : bool
    {
        return isset($this->skipped[spl_object_hash($task)]);
    }

    public function remove(Task $task): void
    {
        $id = spl_object_hash($task);
        if (isset($this->queue[$id])) {
            $this->shouldRunning = null;
            unset($this->queue[$id]);
        }
    }

    /**
     * @return array{queue: array<string, Task>, skip: array<string, Task>}
     */
    public function getQueueProcessed(): array
    {
        if ($this->shouldRunning !== null) {
            return [
                'queue' => $this->shouldRunning,
                'skip'  => $this->skipped,
            ];
        }

        uasort(
            $this->queue,
            function (Task $a, Task $b) {
                $a = $this->getRecordLoader()->getRecord($a)?->getLastExecutionTime()??[0];
                $b = $this->getRecordLoader()->getRecord($b)?->getLastExecutionTime()??[0];
                return $a === $b ? 0 : ($a < $b ? -1 : 1);
            }
        );

        $queues = $this->queue;
        $this->shouldRunning = [];
        while ($queue = array_shift($queues)) {
            $id = spl_object_hash($queue);
            if ($this->shouldRun($queue)) {
                $this->shouldRunning[$id] = $queue;
                continue;
            }
            $this->skipped[$id] = $queue;
        }
        return [
            'queue' => $this->shouldRunning,
            'skip' => $this->skipped,
        ];
    }

    /**
     * @param ?int $timeout in seconds. if after processed time greater than time scheduler will stopped
     * @return int
     */
    public function run(?int $timeout = null): int
    {
        if (($count = count($this->progress))) {
            throw new RuntimeException(
                sprintf(
                    $this->translatePluralContext(
                        'Scheduler is on progress with (%s) task remaining',
                        'Scheduler is on progress with (%s) tasks remaining',
                        $count,
                        'scheduler'
                    ),
                    $count
                )
            );
        }

        $this->getQueueProcessed();
        try {
            $startTime = time();
            $timedOut = is_int($timeout) && $timeout > 0
                ? $startTime + $timeout
                : null;
            $manager = $this->getManager();
            $processed = 0;
            if ($this->shouldRunning === null) {
                $this->getQueueProcessed();
                $this->shouldRunning ??= [];
            }
            foreach ($this->shouldRunning as $id => $queue) {
                unset($this->queue[$id], $this->shouldRunning[$id]);
                $this->progress[$id] = $queue;
            }
            // reset
            $this->shouldRunning = null;
            foreach ($this->progress as $id => $queue) {
                $time = time();
                if ($timedOut !== null && $time > $timedOut) {
                    break;
                }
                // create current time
                $record = $this->getRecordLoader()->getRecord($queue)??new LastRecord(
                    $queue,
                    $time,
                    null
                );
                // increment
                $processed++;
                // new runner
                $runner = new Runner(
                    $this,
                    $queue,
                    $record,
                    $time
                );
                $ended = false;
                $manager?->dispatch(
                    'scheduler.beforeProcessing',
                    $queue,
                    $runner,
                    $time,
                    $this
                );

                // on exit
                register_shutdown_function(
                    function () use (
                        $manager,
                        $time,
                        $id,
                        $runner,
                        $queue,
                        &$ended
                    ) {
                        if ($ended) {
                            return;
                        }
                        if ($runner->getStatusCode() === Runner::STATUS_PROGRESS) {
                            (function () {
                                $this->{'status'} = Runner::STATUS_EXITED;
                            })->call($runner);
                        }
                        unset($this->progress[$id]);
                        $this->finished[$id] = $runner;
                        $this->getRecordLoader()->storeExitRunner(
                            $runner,
                            $this
                        );
                        $manager?->dispatch(
                            'scheduler.afterProcessing',
                            $queue,
                            $runner,
                            $time,
                            $this
                        );
                        $manager?->dispatch(
                            'scheduler.exiting',
                            $queue,
                            $runner,
                            $time,
                            $this
                        );
                    }
                );

                try {
                    // do process
                    $runner->process();
                    $ended = true;
                    $manager?->dispatch('scheduler.processing', $queue, $runner, $time, $this);
                } finally {
                    $ended = true;
                    // add records execution time & status
                    $this->getRecordLoader()->finish($time, $runner, $this);
                    // done
                    $this->finished[$id] = $runner;
                    // remove progress
                    unset($this->progress[$id]);
                    $manager?->dispatch(
                        'scheduler.afterProcessing',
                        $queue,
                        $runner,
                        $time,
                        $this
                    );
                }
            }

            // put back if time out
            foreach ($this->progress as $id => $queue) {
                unset($this->progress[$id]);
                $this->queue[$id] = $queue;
            }
        } finally {
            return $processed;
        }
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this);
    }
}
