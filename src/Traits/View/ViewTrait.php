<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\View;

use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Psr\Container\ContainerInterface;

trait ViewTrait
{
    abstract public function getContainer(): ?ContainerInterface;

    /**
     * Get View
     *
     * @return ?ViewInterface
     */
    public function getView() : ?ViewInterface
    {
        return ContainerHelper::use(
            ViewInterface::class,
            $this->getContainer()
        );
    }
}
