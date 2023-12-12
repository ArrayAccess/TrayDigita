<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Interfaces;

interface UnInvokableInterface
{
    /**
     * Disable invoke
     *
     * @throws \LogicException if calling as callable
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function __invoke();
}
