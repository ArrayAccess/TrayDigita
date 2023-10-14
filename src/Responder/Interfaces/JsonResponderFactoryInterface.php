<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Interfaces;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use Psr\Container\ContainerInterface;

interface JsonResponderFactoryInterface
{
    public function createJsonResponder(
        ContainerInterface $container = null,
        ManagerInterface $manager = null
    ) : JsonResponderInterface;
}
