<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;

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

    private bool $translated = false;

    public function getName(): string
    {
        if ($this->translated) {
            return $this->name;
        }
        $this->translated = true;
        return $this->name = ContainerHelper::use(TranslatorInterface::class)
            ?->translate('Event', context: 'benchmark')??$this->name;
    }

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
