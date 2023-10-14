<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces;

use IteratorAggregate;

interface MetadataCollectionInterface extends IteratorAggregate
{
    public function __construct(SingleMetadataInterface ...$metadata);

    public static function createMetadata(string $key, mixed $value) : SingleMetadataInterface;

    public function add(SingleMetadataInterface ...$metadata);

    public function remove(SingleMetadataInterface|string $metadata);

    public function get(string $key) : ?SingleMetadataInterface;

    public function has(string|SingleMetadataInterface $metadata): bool;

    /**
     * @return array<SingleMetadataInterface>
     */
    public function getMetadata() : array;
}
