<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;

class DatabaseAggregator extends AbstractAggregator
{
    protected string $name = 'Database';

    protected string $groupName = 'database';
}
