<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter;

use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\SingleMetadataInterface;

class SingleMetadata implements SingleMetadataInterface
{
    public function __construct(
        protected string $key,
        protected mixed $value
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value()
    {
        return $this->value;
    }
}
