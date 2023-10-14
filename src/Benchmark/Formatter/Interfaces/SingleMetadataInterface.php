<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces;

interface SingleMetadataInterface
{
    public function __construct(string $key, $value);

    public function key() : string;

    public function value();
}
