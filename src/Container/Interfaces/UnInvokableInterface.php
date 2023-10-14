<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Interfaces;

use LogicException;

interface UnInvokableInterface
{
    /**
     * @throws LogicException if calling as callable
     */
    public function __invoke();
}
