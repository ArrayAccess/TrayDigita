<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;

class EventAggregator extends AbstractAggregator
{
    protected string $name = 'Event';

    protected string $groupName = 'event';

    protected array $blackListed = [
        'cache' => true,
        'app' => true,
        'translator' => true,
        'view' => true,
        'controller' => true,
        'route' => true,
        'middleware' => true,
        'middlewareDispatcher' => true,
        'httpKernel' => true,
        'kernel' => true,
        'database' => true,
        'module' => true,
        'response' => true,
        'viewEngine' => true,
        'assetsJsCssQueue' => true,
    ];

    public function addBlacklistedGroup(string $name): void
    {
        $this->blackListed[$name] = true;
    }

    public function removeBlacklistedGroup(string $name): void
    {
        unset($this->blackListed[$name]);
    }

    public function accepted(RecordInterface $record): bool
    {
        return !isset($this->blackListed[$record->getGroup()->getName()]);
    }
}
