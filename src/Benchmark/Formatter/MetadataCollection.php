<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter;

use ArrayIterator;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\MetadataCollectionInterface;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\SingleMetadataInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use Traversable;

class MetadataCollection implements MetadataCollectionInterface
{
    private array $metadata = [];

    public function __construct(SingleMetadataInterface ...$metadata)
    {
        $this->add(...$metadata);
    }

    public static function createFromBenchmarkRecord(RecordInterface $record): static
    {
        $obj = new static();
        foreach ($record->getMetadata() as $key => $meta) {
            $obj->add(static::createMetadata((string) $key, $meta));
        }
        return $obj;
    }

    public static function createMetadata(string $key, mixed $value): SingleMetadataInterface
    {
        return new SingleMetadata($key, $value);
    }

    public function add(SingleMetadataInterface ...$metadata) : void
    {
        foreach ($metadata as $meta) {
            $this->metadata[$meta->key()] = $meta;
        }
    }

    public function get(string $key) : ?SingleMetadataInterface
    {
        return $this->metadata[$key]??null;
    }

    public function remove(string|SingleMetadataInterface $metadata) : ?SingleMetadataInterface
    {
        $key = is_string($metadata) ? $metadata : $metadata->key();
        $metadata = $this->metadata[$key]??null;
        unset($this->metadata[$key]);
        return $metadata;
    }

    public function has(string|SingleMetadataInterface $metadata): bool
    {
        $key = is_string($metadata) ? $metadata : $metadata->key();
        return isset($this->metadata[$key]);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return Traversable<SingleMetadataInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getMetadata());
    }
}
