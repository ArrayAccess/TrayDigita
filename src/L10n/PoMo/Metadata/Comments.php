<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Metadata;

use ArrayIterator;
use Countable;
use Traversable;
use function array_search;
use function array_splice;
use function in_array;

final class Comments implements Countable
{
    private array $comments = [];

    public function __construct(string ...$flags)
    {
        $this->add(...$flags);
    }

    public function add(string ...$flags) : self
    {
        foreach ($flags as $flag) {
            if (!$this->has($flag)) {
                $this->comments[] = $flag;
            }
        }

        return $this;
    }

    public function remove(string ...$flags) : self
    {
        foreach ($flags as $flag) {
            if (($key = array_search($flag, $this->comments)) !== false) {
                array_splice($this->comments, $key, 1);
            }
        }

        return $this;
    }

    public function has(string $flag) : bool
    {
        return in_array($flag, $this->comments, true);
    }

    public function toArray() : array
    {
        return $this->comments;
    }

    public function mergeWith(Comments $flags) : Comments
    {
        $obj = clone $this;
        $obj->add(...$flags->toArray());
        return $obj;
    }

    public function count() : int
    {
        return count($this->comments);
    }

    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->comments);
    }
}
