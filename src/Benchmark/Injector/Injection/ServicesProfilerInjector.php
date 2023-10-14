<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

class ServicesProfilerInjector extends AbstractBasedCoreInjector
{
    protected function isAllowedGroup(string $group): bool
    {
        return match ($group) {
            'app',
            'translator',
            'view',
            'controller',
            'cache',
            'assetsJsCssQueue',
            'viewEngine',
            'module',
            'template' => true,
            default => false
        };
    }
}
