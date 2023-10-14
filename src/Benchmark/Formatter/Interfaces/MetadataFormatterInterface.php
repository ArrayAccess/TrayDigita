<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces;

interface MetadataFormatterInterface
{
    public function format(MetadataCollectionInterface $metadataCollection) : FormattedDataInterface;
}
