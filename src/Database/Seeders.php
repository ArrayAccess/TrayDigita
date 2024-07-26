<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\Common\DataFixtures\Loader;

class Seeders extends Loader implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait;

    public function __construct(
        public readonly Connection $connection
    ) {
        $this->setContainer($connection->getContainer());
        $manager = $this->connection->getManager();
        if (!$manager) {
            $manager = ContainerHelper::service(
                ManagerInterface::class,
                $this->getContainer()
            );
        }
        if ($manager instanceof ManagerInterface) {
            $this->setManager($manager);
        }
    }
}
