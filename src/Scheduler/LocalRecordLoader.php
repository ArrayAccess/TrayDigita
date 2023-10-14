<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\RecordLoaderInterface;
use ArrayAccess\TrayDigita\Scheduler\Messages\Exited;
use ArrayAccess\TrayDigita\Scheduler\Messages\Failure;
use ArrayAccess\TrayDigita\Scheduler\Messages\Progress;
use ArrayAccess\TrayDigita\Scheduler\Messages\Skipped;
use ArrayAccess\TrayDigita\Scheduler\Messages\Stopped;
use ArrayAccess\TrayDigita\Scheduler\Messages\Success;
use ArrayAccess\TrayDigita\Scheduler\Messages\Unknown;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataType;
use Psr\Container\ContainerInterface;
use Stringable;
use function array_shift;
use function base64_decode;
use function debug_backtrace;
use function explode;
use function fclose;
use function file;
use function flock;
use function fopen;
use function fwrite;
use function is_dir;
use function is_file;
use function is_resource;
use function is_string;
use function is_subclass_of;
use function is_writable;
use function mkdir;
use function sha1;
use function sprintf;
use function strlen;
use function sys_get_temp_dir;
use function trim;
use function unserialize;
use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_SH;
use const LOCK_UN;

class LocalRecordLoader implements RecordLoaderInterface, ContainerAllocatorInterface
{
    use ContainerAllocatorTrait;

    /**
     * @var ?array<string, LastRecord>
     */
    protected ?array $executionRecords = null;

    private ?string $recordFile = null;

    protected int $finished = 0;

    protected bool $containChanged = false;

    public function __construct(
        ?ContainerInterface $container = null
    ) {
        if ($container) {
            $this->setContainer($container);
        }
    }

    public function getRecord(Task $task): ?LastRecord
    {
        $hasName = $this->taskNameHash($task);
        if ($this->executionRecords === null) {
            $container = $this->getContainer();
            $this->executionRecords = [];
            $config = ContainerHelper::use(Config::class, $container);
            if ($config instanceof Config) {
                $config = $config->get('path');
                $storage = $config instanceof Config
                    ? $config->get('storage')
                    : null;
            }
            $project_id = sha1(__DIR__);
            $storage = ($storage ?? sys_get_temp_dir()) . '/scheduler';
            if (!is_dir($storage)) {
                Consolidation::callbackReduceError(
                    static fn() => mkdir($storage, 0755, true)
                );
            }
            if (!is_dir($storage) || !is_writable($storage)) {
                return $this->executionRecords[$hasName] ??= new LastRecord(
                    task: $task,
                    lastExecutionTime: 0,
                    message: null
                );
            }
            $this->recordFile = sprintf('%s/scheduler_%s.txt', $storage, $project_id);
            if (is_file($this->recordFile)) {
                $this->containChanged = false;
                $handle = Consolidation::callbackReduceError(
                    fn () => fopen($this->recordFile, 'r')
                );
                if (!$handle) {
                    return null;
                }
                $return = false;
                $c = 0;
                while (!$return && $c++ < 10000) {
                    $return = flock($handle, LOCK_SH);
                }
                if (!$return) {
                    fclose($handle);
                    return null;
                }
                foreach (file($this->recordFile) as $data) {
                    $data = trim($data);
                    if ($data === '') {
                        $this->containChanged = true;
                        continue;
                    }
                    $data = explode('|', $data, 4);
                    if (count($data) < 3) {
                        $this->containChanged = true;
                        continue;
                    }
                    $id   = array_shift($data);
                    $time = array_shift($data);
                    $code = array_shift($data);
                    if (strlen($id) !== 40
                        || !DataType::isLowerHex($id)
                        || strlen($time) !== 10
                        || !DataType::isNumberOnly($time)
                        || $code === ''
                        || strlen($code) > 2
                        || !DataType::isNumberOnly($code)
                    ) {
                        $this->containChanged = true;
                        continue;
                    }
                    $data = array_shift($data);
                    if ($data && !DataType::isBase64(trim($data))) {
                        $this->containChanged = true;
                        continue;
                    }
                    $data = Consolidation::callbackReduceError(
                        static fn() => is_string($data) ? unserialize(base64_decode($data)) : null
                    );
                    $code = (int) $code;
                    if (!$data instanceof MessageInterface
                        && $data !== null
                    ) {
                        $data = $data === false
                            ? null
                            : (is_string($data) || $data instanceof Stringable ? $data : null);
                        $data = match ($code) {
                            Runner::STATUS_SKIPPED => new Skipped($data),
                            Runner::STATUS_FAILURE => new Failure($data),
                            Runner::STATUS_EXITED => new Exited($data),
                            Runner::STATUS_PROGRESS => new Progress($data),
                            Runner::STATUS_STOPPED => new Stopped($data),
                            Runner::STATUS_SUCCESS => new Success($data),
                            default => new Unknown($data),
                        };
                    }
                    $this->executionRecords[$id] = (new LastRecord(
                        task: $task,
                        lastExecutionTime: (int) $time,
                        message: $data
                    ))->withStatusCode($code);
                }
                flock($handle, LOCK_UN);
                fclose($handle);
            }
            if ($this->containChanged) {
                $this->doSaveRecords();
            }
        }

        $taskName = $this->taskNameHash($task);
        return $this->executionRecords[$taskName]??null;
    }

    public function taskNameHash(Task $task): string
    {
        return sha1($task->getIdentity() . $task::class);
    }

    public function storeExitRunner(
        Runner $runner,
        Scheduler $scheduler
    ): ?LastRecord {
        if ($this::class === __CLASS__) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
            if (!isset($trace['class'])
                || ($trace['class'] !== Scheduler::class
                    && !is_subclass_of($trace['class'], Scheduler::class)
                )
            ) {
                return null;
            }
        }

        $hashId = $this->taskNameHash($runner->getTask());
        $statusCode =  $runner->isProgress()
            ? Runner::STATUS_EXITED
            : $runner->getStatusCode();
        $this->executionRecords[$hashId] = $runner
            ->getLastRecord()
            ->withStatusCode($statusCode);

        $this->containChanged = true;
        $this->doSaveRecords();
        return $this->executionRecords[$hashId];
    }

    /**
     * Save the records that in progress
     *
     * @param Runner $runner
     * @param Scheduler $scheduler
     * @return ?LastRecord
     */
    public function doStartProgress(
        Runner $runner,
        Scheduler $scheduler
    ): ?LastRecord {
        /** @noinspection DuplicatedCode */
        if ($this::class === __CLASS__) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
            if (!isset($trace['class'])
                || ($trace['class'] !== Runner::class
                    && !is_subclass_of($trace['class'], Runner::class)
                )
                || ($trace['function'] ?? null) !== 'process'
            ) {
                return null;
            }
        }

        $hashId = $this->taskNameHash($runner->getTask());
        $record = $runner->getLastRecord();
        if ($record->getStatusCode() !== Runner::STATUS_PROGRESS) {
            $record = $record->withStatusCode(Runner::STATUS_PROGRESS);
        }
        $this->executionRecords[$hashId] = $record;

        // save progress
        $this->containChanged = true;
        $this->doSaveRecords();

        return $this->executionRecords[$hashId];
    }

    /**
     * Save the records that in progress
     *
     * @param Runner $runner
     * @param Scheduler $scheduler
     * @return ?LastRecord
     */
    public function doSkipProgress(
        Runner $runner,
        Scheduler $scheduler
    ): ?LastRecord {
        /** @noinspection DuplicatedCode */
        if ($this::class === __CLASS__) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
            if (!isset($trace['class'])
                || ($trace['class'] !== Runner::class
                    && !is_subclass_of($trace['class'], Runner::class)
                )
                || ($trace['function'] ?? null) !== 'process'
            ) {
                return null;
            }
        }

        $hashId = $this->taskNameHash($runner->getTask());
        $this->executionRecords[$hashId] = $runner
            ->getLastRecord()
            ->withStatusCode(Runner::STATUS_SKIPPED);

        // do not change skipped
        // $this->containChanged = true;
        // $this->doSaveRecords();

        return $this->executionRecords[$hashId];
    }

    public function finish(
        int $executionTime,
        Runner $runner,
        Scheduler $scheduler
    ): ?LastRecord {
        if ($this::class === __CLASS__) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
            if (!isset($trace['class'])
                || ($trace['class'] !== Scheduler::class
                    && !is_subclass_of($trace['class'], Scheduler::class)
                )
                || ($trace['function'] ?? null) !== 'run'
            ) {
                return null;
            }
        }
        $this->finished++;
        $hashId = $this->taskNameHash($runner->getTask());

        $record = $runner->getLastRecord();
        if ($record->getStatusCode() === Runner::STATUS_PROGRESS
            || $record->getStatusCode() === Runner::STATUS_QUEUE
        ) {
            $statusCode = $runner->getMessage()?->getStatusCode() ?? Runner::STATUS_STOPPED;
            if ($statusCode === Runner::STATUS_QUEUE
                || $statusCode === Runner::STATUS_PROGRESS
            ) {
                // use success as default
                $statusCode = Runner::STATUS_SUCCESS;
            }
            $record = $record->withStatusCode($statusCode);
        }

        $this->executionRecords[$hashId] = $record;

        $this->containChanged = true;
        // save finished
        $this->doSaveRecords(true);
        return $this->executionRecords[$hashId];
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function doSaveRecords(bool $isFinish = false): void
    {
        if (!$this->containChanged) {
            return;
        }

        if (!$this->recordFile
            || empty($this->executionRecords)
        ) {
            return;
        }

        $this->containChanged = false;
        $stream = Consolidation::callbackReduceError(
            fn () => fopen($this->recordFile, 'wb+')
        );

        if (!is_resource($stream)) {
            return;
        }

        flock($stream, LOCK_SH);
        if (!flock($stream, LOCK_EX | LOCK_NB)) {
            fclose($stream);
            return;
        }

        $c = 0;
        foreach ($this->executionRecords as $key => $record) {
            $data = "$key|$record";
            if ($c++ > 0) {
                $data = "\n$data";
            }
            fwrite($stream, $data);
        }
        fclose($stream);
    }

    public function __destruct()
    {
        if (!$this->containChanged && $this->finished === 0) {
            return;
        }

        $this->doSaveRecords();
    }
}
