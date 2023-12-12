<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Database;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

trait ConnectionTrait
{
    abstract public function getContainer(): ?ContainerInterface;

    /**
     * @return Connection
     * @uses \ArrayAccess\TrayDigita\Kernel\Decorator::resolveInternal()
     */
    public function getConnection() : Connection
    {
        // fallback
        return ContainerHelper::service(
            Connection::class,
            $this->getContainer()
        );
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->getConnection()->getEntityManager();
    }
}
