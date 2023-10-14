<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Metadata;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use function array_search;
use function array_splice;
use function in_array;

final class Flags implements Countable, IteratorAggregate
{
    private array $flags = [];

    public function __construct(string ...$flags)
    {
        !empty($flags) && $this->add(...$flags);
    }

    public function add(string ...$flags) : self
    {
        foreach ($flags as $flag) {
            !$this->has($flag) && $this->flags[] = $flag;
        }

        sort($this->flags);
        return $this;
    }

    public function remove(string ...$flags) : self
    {
        foreach ($flags as $flag) {
            ($key = array_search($flag, $this->flags)) !== false &&
            array_splice($this->flags, $key, 1);
        }

        return $this;
    }

    public function has(string $flag) : bool
    {
        return in_array($flag, $this->flags, true);
    }

    public function toArray() : array
    {
        return $this->flags;
    }

    public function mergeWith(Flags $flags) : Flags
    {
        $obj = clone $this;
        $obj->add(...$flags->toArray());
        return $obj;
    }

    public function count() : int
    {
        return count($this->flags);
    }

    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->flags);
    }
}
