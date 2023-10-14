<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;

class ServicesAggregator extends AbstractAggregator
{
    protected string $name = 'Service';

    protected string $groupName = 'service';

    protected array $accepted = [
        'cache' => true,
        'app' => true,
        'translator' => true,
        'view' => true,
        'controller' => true,
        'route' => true,
        'response' => true,
        'template' => true,
        'module' => true,
        'viewEngine' => true,
        'assetsJsCssQueue' => true,
    ];

    public function addAcceptedGroup(string $name): void
    {
        $this->accepted[$name] = true;
    }

    public function removeAcceptedGroup(string $name): void
    {
        unset($this->accepted[$name]);
    }

    public function accepted(RecordInterface $record): bool
    {
        return isset($this->accepted[$record->getGroup()->getName()]);
    }
}
