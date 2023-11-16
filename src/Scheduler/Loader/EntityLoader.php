<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Loader;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Entities\TaskScheduler;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use ArrayAccess\TrayDigita\Scheduler\LastRecord;
use ArrayAccess\TrayDigita\Scheduler\Messages\Exited;
use ArrayAccess\TrayDigita\Scheduler\Messages\Failure;
use ArrayAccess\TrayDigita\Scheduler\Messages\Progress;
use ArrayAccess\TrayDigita\Scheduler\Messages\Skipped;
use ArrayAccess\TrayDigita\Scheduler\Messages\Stopped;
use ArrayAccess\TrayDigita\Scheduler\Messages\Success;
use ArrayAccess\TrayDigita\Scheduler\Messages\Unknown;
use ArrayAccess\TrayDigita\Scheduler\Runner;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use Doctrine\ORM\Tools\SchemaTool;
use Stringable;
use Throwable;
use function dirname;
use function is_string;
use const DIRECTORY_SEPARATOR;

class EntityLoader extends LocalRecordLoader
{
    public const FINISH = 'finish';
    public const PROGRESS = 'progress';
    public const SKIPPED = 'skipped';
    public const EXITED = 'exited';

    private bool $checkedEntity = false;

    public function __construct(protected Connection $connection)
    {
        parent::__construct($this->connection->getContainer());
        $this->connection->registerEntityDirectory(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Entities'
        );
    }

    private function checkEntityRegistration(): void
    {
        if ($this->checkedEntity) {
            return;
        }
        $this->checkedEntity = true;
        try {
            $entity = $this->connection->getEntityManager();
            $factory = $entity->getMetadataFactory();
            if ($factory->hasMetadataFor(TaskScheduler::class)) {
                return;
            }
            $metadata = $factory->getMetadataFor(TaskScheduler::class);
            $schema = $this->connection->createSchemaManager();
            if (!$schema->introspectTable($metadata->getTableName())) {
                $table = (new SchemaTool($entity))
                    ->getSchemaFromMetadata([$metadata])
                    ->getTable($metadata->getTableName());
                $schema->createTable($table);
            }
        } catch (Throwable) {
        }
    }

    private function getRecordEntity(Task $task) : ?TaskScheduler
    {
        $this->checkEntityRegistration();

        $id = $this->taskNameHash($task);
        return $this
            ->connection
            ->find(
                TaskScheduler::class,
                $id
            );
    }

    public function getRecord(Task $task): ?LastRecord
    {
        $id = $this->taskNameHash($task);
        if (isset($this->lastRecords[$id])) {
            return $this->lastRecords[$id];
        }
        $entity = $this->getRecordEntity($task);
        if (!$entity) {
            return null;
        }

        $lastExecutionTime = $entity->getExecutionTime();
        $statusCode = $entity->getStatusCode();
        $message = $entity->getMessage();
        if ($message instanceof LastRecord) {
            $messageLastExecutionTime = $message->getLastExecutionTime();
            $messageStatusCode = $message->getStatusCode();
            $message = $message->getMessage();
            if (($statusCode === Runner::STATUS_UNKNOWN
                || $statusCode === Runner::STATUS_QUEUE)
                && (
                    $messageStatusCode !== Runner::STATUS_UNKNOWN
                    && $messageStatusCode !== Runner::STATUS_PROGRESS
                )
            ) {
                $statusCode = $message->getStatusCode();
            }
            if ($messageLastExecutionTime > Runner::PREVIOUS_MIN_TIME
                && ($lastExecutionTime === 0 || $lastExecutionTime)) {
                $lastExecutionTime = $messageLastExecutionTime;
            }
        } elseif (!$message instanceof MessageInterface) {
            if ($message !== null
                && !is_string($message)
                && !$message instanceof Stringable
            ) {
                $message = null;
            }
            $message = match ($entity->getStatusCode()) {
                Runner::STATUS_SKIPPED => new Skipped($message),
                Runner::STATUS_FAILURE => new Failure($message),
                Runner::STATUS_EXITED => new Exited($message),
                Runner::STATUS_PROGRESS => new Progress($message),
                Runner::STATUS_STOPPED => new Stopped($message),
                Runner::STATUS_SUCCESS => new Success($message),
                default => new Unknown($message),
            };
        }

        return $this->executionRecords[$id] = (new LastRecord(
            $task,
            $lastExecutionTime,
            $message
        ))->withStatusCode($statusCode);
    }

    private function saveRecord(LastRecord $record, Runner $runner, ?string $status = null): void
    {
        $isFinish = $status === self::FINISH;
        switch ($status) {
            case self::FINISH:
            case self::PROGRESS:
            case self::SKIPPED:
            case self::EXITED:
                $status = match ($status) {
                    self::PROGRESS => Runner::STATUS_PROGRESS,
                    self::SKIPPED => Runner::STATUS_SKIPPED,
                    self::EXITED => Runner::STATUS_EXITED,
                    default => $record->getStatusCode()
                };
                $task = $record->getTask();
                $entity = $this->getRecordEntity($task);
                if (!$entity) {
                    $entity = new TaskScheduler();
                    $entity->setName($task->getName());
                    $entity->setIdentity($this->taskNameHash($task));
                }
                if ($isFinish || $status === Runner::STATUS_EXITED) {
                    $entity->setExecuteDuration($runner->getExecutionDuration());
                }

                $entity->setFinishTime(
                    $isFinish ? time() : $entity->getFinishTime()
                );
                $entity->setExecutedObjectClass($task::class);
                $entity->setExecutionTime($record->getLastExecutionTime());
                $entity->setMessage($record->getMessage());
                $entity->setStatusCode($status);
                $em = $this->connection->getEntityManager();
                $em->persist($entity);
                $em->flush();
                return;
        }
    }

    public function storeExitRunner(Runner $runner, Scheduler $scheduler): ?LastRecord
    {
        $record = parent::storeExitRunner($runner, $scheduler);
        if ($record) {
            $this->saveRecord($record, $runner, self::EXITED);
        }
        return $record;
    }

    public function doSkipProgress(Runner $runner, Scheduler $scheduler): ?LastRecord
    {
        $record = parent::doSkipProgress($runner, $scheduler);
        if ($record) {
            $this->saveRecord($record, $runner, self::SKIPPED);
        }
        return $record;
    }

    public function doStartProgress(Runner $runner, Scheduler $scheduler): ?LastRecord
    {
        $record = parent::doStartProgress($runner, $scheduler);
        if ($record) {
            $this->saveRecord($record, $runner, self::PROGRESS);
        }
        return $record;
    }

    public function finish(int $executionTime, Runner $runner, Scheduler $scheduler): LastRecord
    {
        $record = parent::finish($executionTime, $runner, $scheduler);
        if ($record) {
            $this->saveRecord($record, $runner, self::FINISH);
        }
        return $record;
    }

    protected function doSaveRecords(bool $isFinish = false): void
    {
    }
}
