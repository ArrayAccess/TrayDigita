<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

use ArrayAccess\TrayDigita\Benchmark\Injector\ManagerProfiler;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use SensitiveParameter;
use function var_dump;

class ManagerProfilerInjector extends AbstractBasedCoreInjector
{
    protected bool $coreOnly = false;

    protected function isAllowedGroup(string $group): bool
    {
        return true;
    }

    protected function isAllowedName(string $group, string $name): bool
    {
        return true;
    }

    protected function appendToRecord(): bool
    {
        return false;
    }

    public function acceptedRecord(ManagerInterface $manager, string $eventName, ?string $id): bool
    {
        if ($this->getStaticRecord($eventName, $id)) {
            return false;
        }
        return parent::acceptedRecord($manager, $eventName, $id);
    }

    /**
     * @inheritdoc
     */
    public function start(
        ManagerProfiler $managerProfiler,
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        #[SensitiveParameter] $originalParam,
        #[SensitiveParameter] $param,
        #[SensitiveParameter] ...$arguments
    ): ?static {
        if (!$this->acceptedRecord($manager, $eventName, $id)) {
            return null;
        }
        // skip if disabled
        if (!$this->getProfilerManager()->getProfiler()->isEnable()) {
            return null;
        }
        $group = $this->getEventGroup($eventName);
        $this->singleBenchmarks[$eventName][$id] = $this
            ->getProfilerManager()
            ->getProfiler()
            ->start(
                $eventName,
                $group,
                $this->getMetadata($eventName, $originalParam, $param, ...$arguments)
            );

        return $this;
    }
}
