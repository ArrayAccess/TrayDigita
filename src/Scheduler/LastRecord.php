<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler;

use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use Stringable;
use Throwable;
use function base64_encode;
use function serialize;
use function sprintf;

final class LastRecord implements Stringable
{
    private int $statusCode;

    public function __construct(
        private readonly Task $task,
        protected int $lastExecutionTime,
        protected ?MessageInterface $message
    ) {
        $this->statusCode = $this->message?->getStatusCode()??Runner::STATUS_UNKNOWN;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getLastExecutionTime(): int
    {
        return $this->lastExecutionTime;
    }

    public function getMessage(): ?MessageInterface
    {
        return $this->message;
    }
    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    public function withMessage(?MessageInterface $message): LastRecord
    {
        $object = clone $this;
        $object->message = $message;
        $object->statusCode = $message?->getStatusCode()??$object->statusCode;
        return $object;
    }

    public function withStatusCode(int $statusCode): LastRecord
    {
        $object = clone $this;
        $object->statusCode = $statusCode;
        return $object;
    }

    public function withLastExecutionTime(int $lastExecutionTime): LastRecord
    {
        $object = clone $this;
        $object->lastExecutionTime = $lastExecutionTime;
        return $object;
    }

    public function __toString(): string
    {
        try {
            $message = serialize($this->getMessage());
        } catch (Throwable) {
            $message = (string) $this->getMessage()?->getMessage();
        }
        return sprintf(
            '%s|%s|%s',
            $this->getLastExecutionTime(),
            $this->getStatusCode(),
            base64_encode($message)
        );
    }
}
