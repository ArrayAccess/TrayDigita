<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;

class DatabaseAggregator extends AbstractAggregator
{
    protected string $name = 'Database';

    protected string $groupName = 'database';

    private bool $translated = false;

    public function getName(): string
    {
        if ($this->translated) {
            return $this->name;
        }
        $this->translated = true;
        return $this->name = ContainerHelper::use(TranslatorInterface::class)
            ?->translate('Database', context: 'benchmark')??$this->name;
    }
}
