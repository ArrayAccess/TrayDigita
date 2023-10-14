<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Factory;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonResponderFactoryInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonResponderInterface;
use ArrayAccess\TrayDigita\Responder\JsonResponder;
use Psr\Container\ContainerInterface;

class JsonResponderFactory implements JsonResponderFactoryInterface
{
    public function createJsonResponder(
        ContainerInterface $container = null,
        ManagerInterface $manager = null
    ): JsonResponderInterface {
        return new JsonResponder($container, $manager);
    }
}
