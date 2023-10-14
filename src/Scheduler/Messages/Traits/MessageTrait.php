<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Messages\Traits;

use Stringable;
use function serialize;
use function unserialize;

trait MessageTrait
{
    public function __construct(public null|string|Stringable $message)
    {
    }

    public function getMessage(): string|Stringable
    {
        return $this->message;
    }

    public function __toString(): string
    {
        return (string) $this->getMessage();
    }

    public function serialize(): string
    {
        return serialize($this->message);
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __serialize(): array
    {
        return ['message' => $this->message];
    }

    public function __unserialize(array $data): void
    {
        $this->message = $data['message'];
    }
}
