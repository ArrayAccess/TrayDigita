<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

class RouteProfilerInjector extends AbstractBasedCoreInjector
{
    protected function getBenchmarkGroupName(): ?string
    {
        return 'route';
    }

    protected function isAllowedGroup(string $group): bool
    {
        return match ($group) {
            'routeRunner',
            'router',
            'route' => true,
            default => false
        };
    }
}
