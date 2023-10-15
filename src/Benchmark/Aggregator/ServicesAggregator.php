<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Aggregator;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\AbstractAggregator;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;

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

    private bool $translated = false;

    public function getName(): string
    {
        if ($this->translated) {
            return $this->name;
        }
        $this->translated = true;
        return $this->name = ContainerHelper::use(TranslatorInterface::class)
            ?->translateContext('Service', 'benchmark')??$this->name;
    }

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
