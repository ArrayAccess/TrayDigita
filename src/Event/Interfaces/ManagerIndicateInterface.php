<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Event\Interfaces;

interface ManagerIndicateInterface
{
    /**
     * Get the event manager
     *
     * @return ?ManagerInterface
     */
    public function getManager() : ?ManagerInterface;
}
