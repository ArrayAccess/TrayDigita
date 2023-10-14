<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

class DatabaseProfilerInjector extends AbstractBasedCoreInjector
{
    protected function getBenchmarkGroupName(): ?string
    {
        return 'database';
    }

    protected function isAllowedGroup(string $group): bool
    {
        return match ($group) {
            'connection',
            'entityManager',
            'entityRepository',
            'database' => true,
            default => false
        };
    }
}
