<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Event\Interfaces;

interface ManagerAllocatorInterface extends ManagerIndicateInterface
{
    /**
     * Set event manager
     *
     * @param ManagerInterface $manager
     * @return ?ManagerInterface return previous manager if exists
     */
    public function setManager(ManagerInterface $manager) : ?ManagerInterface;
}
