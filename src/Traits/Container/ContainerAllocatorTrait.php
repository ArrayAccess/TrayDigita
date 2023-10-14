<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Container;

use Psr\Container\ContainerInterface;

trait ContainerAllocatorTrait
{
    protected ?ContainerInterface $containerObject = null;

    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $previous = $this->containerObject;
        $this->containerObject = $container;
        return $previous;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->containerObject;
    }
}
