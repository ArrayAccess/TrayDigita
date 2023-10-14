<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n\Records;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Serializable;
use Traversable;
use function array_values;
use function serialize;
use function unserialize;

final class TimeZones implements Serializable, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @var array<string>
     */
    protected array $timeZones = [];

    public function __construct(string ...$timeZones)
    {
        $this->timeZones = array_values($timeZones);
    }

    /**
     * @return array<string>
     */
    public function getTimeZones(): array
    {
        return $this->timeZones;
    }

    public function count(): int
    {
        return count($this->timeZones);
    }


    public function serialize() : string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __serialize(): array
    {
        return [
            'timeZones' => $this->getTimeZones()
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->timeZones = $data['timeZones'];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getTimeZones());
    }

    public function jsonSerialize(): array
    {
        return $this->getTimeZones();
    }
}
