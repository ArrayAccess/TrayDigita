<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;

interface AggregateInterface
{
    public function aggregate(RecordInterface $record);
}
