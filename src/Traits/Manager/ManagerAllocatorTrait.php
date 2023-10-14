<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Manager;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;

trait ManagerAllocatorTrait
{
    protected ?ManagerInterface $managerObject = null;

    /**
     * Set manager
     *
     * @param ManagerInterface $manager
     * @return ?ManagerInterface
     */
    public function setManager(ManagerInterface $manager): ?ManagerInterface
    {
        $previous = $this->managerObject;
        $this->managerObject = $manager;
        return $previous;
    }

    /**
     * Get manager
     *
     * @return ?ManagerInterface
     */
    public function getManager(): ?ManagerInterface
    {
        return $this->managerObject;
    }
}
