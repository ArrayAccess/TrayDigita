<?php
/** @noinspection PhpMissingReturnTypeInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Collection;

use ArrayIterator;
use ArrayAccess\TrayDigita\Collection\Interfaces\CollectionInterface;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\IterableHelper;
use IteratorAggregate;
use Traversable;
use function array_key_exists;
use function array_keys;
use function in_array;

class Collection implements CollectionInterface, IteratorAggregate
{
    protected array $data;

    public function __construct(iterable $param = [])
    {
        $this->data = IterableHelper::all($param);
    }

    public function get($id, $default = null)
    {
        return array_key_exists($id, $this->data)
            ? $this->data[$id]
            : $default;
    }

    public function set($id, $value)
    {
        $this->data[$id] = $value;
    }

    public function remove($id)
    {
        unset($this->data[$id]);
    }

    public function has($id): bool
    {
        return array_key_exists($id, $this->data);
    }

    public function contain($param): bool
    {
        return in_array($this->data, $param, true);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function keys(): array
    {
        return array_keys($this->data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    // protect from usage
    public function __debugInfo(): ?array
    {

        return Consolidation::debugInfo(
            $this,
            regexP: '~(?i)(?:secret|salt|nonce|key|auth|pass|license|hash)~',
            detectSubKey: true,
            detectParent: false
        );
    }
}
