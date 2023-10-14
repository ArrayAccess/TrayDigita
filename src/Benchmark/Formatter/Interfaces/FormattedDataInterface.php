<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces;

use IteratorAggregate;

interface FormattedDataInterface extends IteratorAggregate
{
    public function __construct(MetadataFormatterInterface $formatter);

    public function getFormatter() : MetadataFormatterInterface;

    public function add(string $key, string $formattedData);

    public function has(string $key) : bool;

    /**
     * @return array<string, string>
     */
    public function getData() : array;
}
