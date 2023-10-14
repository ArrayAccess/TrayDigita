<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Injector\Injection;

class KernelProfilerInjector extends AbstractBasedCoreInjector
{
    protected function getBenchmarkGroupName(): ?string
    {
        return 'kernel';
    }

    protected function isAllowedGroup(string $group): bool
    {
        return match ($group) {
            'kernel',
            'httpKernel' => true,
            default => false
        };
    }
}
