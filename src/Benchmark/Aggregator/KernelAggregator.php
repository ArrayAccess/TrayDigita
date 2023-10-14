<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;

class KernelAggregator extends AbstractAggregator
{
    protected string $name = 'Kernel';

    protected string $groupName = 'kernel';

    protected array $accepted = [
        'httpKernel' => true,
        'kernel' => true,
    ];

    public function accepted(RecordInterface $record): bool
    {
        return isset($this->accepted[$record->getGroup()->getName()]);
    }
}
