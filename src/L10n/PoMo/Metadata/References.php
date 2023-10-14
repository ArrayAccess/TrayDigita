<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Metadata;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use function array_reduce;
use function array_search;
use function array_splice;
use function in_array;

final class References implements Countable, IteratorAggregate
{
    /**
     * @var array<string,int[]>
     */
    private array $references = [];

    /**
     * @param string $fileName
     * @param ?int $line
     *
     * @return $this
     */
    public function add(string $fileName, ?int $line = null) : self
    {
        $fileReferences = $this->references[$fileName] ?? [];

        if (isset($line) && !in_array($line, $fileReferences, true)) {
            $fileReferences[] = $line;
        }

        $this->references[$fileName] = $fileReferences;

        return $this;
    }

    public function remove(string ...$flags) : self
    {
        foreach ($flags as $flag) {
            if (($key = array_search($flag, $this->references)) !== false) {
                array_splice($this->references, $key, 1);
            }
        }

        return $this;
    }

    public function has(string $flag) : bool
    {
        return in_array($flag, $this->references, true);
    }

    /**
     * @return array<string,int[]>
     */
    public function toArray() : array
    {
        return $this->references;
    }

    public function mergeWith(References $references) : References
    {
        $obj = clone $this;
        foreach ($references->toArray() as $filename => $lines) {
            // Set filename always to string
            // $filename = (string) $filename;
            foreach ($lines as $line) {
                $obj->add($filename, $line);
            }
        }

        return $obj;
    }

    public function count() : int
    {
        return array_reduce($this->references, static function ($carry, $item) {
            return $carry + (count($item) ?: 1);
        }, 0);
    }

    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->references);
    }
}
