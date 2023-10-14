<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

use JsonSerializable;

interface RecordInterface extends
    NamingInterface,
    DurationInterface,
    MemoryInterface,
    SeverityInterface,
    MicrotimeConversionInterface,
    JsonSerializable
{

    public function isStopped(): bool;

    public function stop(array $metadata = []): static;

    public function getGroup(): GroupInterface;

    public function getMetadata(): array;

    public function getMetadataRecord(string $key);

    public function removeMetadataRecord(string $key);

    /**
     * Add metadata if not exists
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function addMetadataRecord(string $key, mixed $value) : bool;

    public function setMetadataRecord(string $key, mixed $value);
    public function hasMetadataRecord(string $key) : bool;
}
