<?php
declare(strict_types=1);

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
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionUnionType;
use Throwable;
use function array_shift;
use function call_user_func;
use function count;
use function func_get_args;
use function is_a;
use function is_array;
use function is_int;
use function max;
use function register_shutdown_function;
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
    use ManagerAllocatorTrait;

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
                    'Callback task must be contain return type or instance of: %s',
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
        $this->queue[spl_object_hash($scheduler)] = $scheduler;
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

    public function shouldRun(Task $task) : bool
    {
        $interval = $task->getInterval();
        if ($interval === 0) {
            return false;
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

    /**
     * @return int
     */
    public function run(): int
    {
        if (($count = count($this->progress))) {
            throw new RuntimeException(
                sprintf(
                    'Scheduler is on progress with (%s) %s remaining',
                    $count,
                    $count > 1 ? 'tasks' : 'task'
                )
            );
        }

        try {
            uasort(
                $this->queue,
                function (Task $a, Task $b) {
                    $a = $this->getRecordLoader()->getRecord($a)?->getLastExecutionTime()??[0];
                    $b = $this->getRecordLoader()->getRecord($b)?->getLastExecutionTime()??[0];
                    return $a === $b ? 0 : ($a < $b ? -1 : 1);
                }
            );
            $manager = $this->getManager();
            $processed = 0;
            while ($queue = array_shift($this->queue)) {
                $id = spl_object_hash($queue);
                $record = $this
                    ->getRecordLoader()
                    ->getRecord($queue);

                // create current time
                $time = time();
                // add in progress
                $this->progress[$id] = $queue;
                $record ??= (new LastRecord(
                    $queue,
                    $time,
                    null
                ));
                // increment
                $processed++;
                // new runner
                $runner = new Runner(
                    $this,
                    $queue,
                    $record,
                    $time
                );

                if (!$this->shouldRun($queue)) {
                    $this->skipped[$id] = $queue;
                    $manager?->dispatch(
                        'scheduler.beforeSkipTask',
                        $runner,
                        $time,
                        $this
                    );
                    try {
                        $runner->skip();
                        $manager?->dispatch(
                            'scheduler.skipTask',
                            $runner,
                            $time,
                            $this
                        );
                    } finally {
                        $manager?->dispatch(
                            'scheduler.afterSkipTask',
                            $runner,
                            $time,
                            $this
                        );
                    }
                    continue;
                }

                $ended = false;
                $manager?->dispatch(
                    'scheduler.beforeProcessTask',
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
                            'scheduler.afterProcessTask',
                            $runner,
                            $time,
                            $this
                        );
                    }
                );
                try {
                    // do process
                    $runner->process();
                    $manager?->dispatch(
                        'scheduler.processTask',
                        $runner,
                        $time,
                        $this
                    );
                } finally {
                    $ended = true;
                    // add records execution time & status
                    $this->getRecordLoader()->finish(
                        $time,
                        $runner,
                        $this
                    );
                    // done
                    $this->finished[$id] = $runner;
                    // remove progress
                    unset($this->progress[$id]);
                    $manager?->dispatch(
                        'scheduler.afterProcessTask',
                        $runner,
                        $time,
                        $this
                    );
                }
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
