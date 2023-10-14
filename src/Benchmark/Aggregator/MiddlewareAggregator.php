<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;

class MiddlewareAggregator extends AbstractAggregator
{
    protected string $name = 'Middleware';

    protected string $groupName = 'middleware';

    protected array $accepted = [
        'middleware' => true,
        'middlewareDispatcher' => true,
    ];

    public function accepted(RecordInterface $record): bool
    {
        return isset($this->accepted[$record->getGroup()->getName()]);
    }
}
