<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;

class MiddlewareAggregator extends AbstractAggregator
{
    protected string $name = 'Middleware';

    protected string $groupName = 'middleware';

    protected array $accepted = [
        'middleware' => true,
        'middlewareDispatcher' => true,
    ];

    private bool $translated = false;

    public function getName(): string
    {
        if ($this->translated) {
            return $this->name;
        }
        $this->translated = true;
        return $this->name = ContainerHelper::use(TranslatorInterface::class)
            ?->translateContext('Middleware', 'benchmark')??$this->name;
    }

    public function accepted(RecordInterface $record): bool
    {
        return isset($this->accepted[$record->getGroup()->getName()]);
    }
}
