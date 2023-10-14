<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig\TwigExtensions;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\View\Engines\TwigEngine;

abstract class AbstractExtension extends \Twig\Extension\AbstractExtension implements ContainerIndicateInterface
{
    use ContainerAllocatorTrait;

    public function __construct(protected TwigEngine $engine)
    {
        $this->setContainer($this->engine->getView()->getContainer());
    }
}
