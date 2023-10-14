<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter;

use ArrayIterator;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\FormattedDataInterface;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\MetadataFormatterInterface;
use Traversable;

class FormattedData implements FormattedDataInterface
{
    protected array $data = [];

    public function __construct(protected MetadataFormatterInterface $formatter)
    {
    }

    public function getFormatter(): MetadataFormatterInterface
    {
        return $this->formatter;
    }

    public function add(string $key, string $formattedData): void
    {
        $this->data[$key] = $formattedData;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getData());
    }
}
