<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

class MiddlewareProfilerInjector extends AbstractBasedCoreInjector
{
    protected function getBenchmarkGroupName(): ?string
    {
        return 'middleware';
    }

    protected function isAllowedGroup(string $group): bool
    {
        return match ($group) {
            'middleware',
            'middlewareDispatcher' => true,
            default => false
        };
    }
}
