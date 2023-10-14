<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Factory;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Responder\HtmlResponder;
use ArrayAccess\TrayDigita\Responder\Interfaces\HtmlResponderFactoryInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\HtmlResponderInterface;
use Psr\Container\ContainerInterface;

class HtmlResponderFactory implements HtmlResponderFactoryInterface
{
    public function createHtmlResponder(
        ContainerInterface $container = null,
        ManagerInterface $manager = null
    ): HtmlResponderInterface {
        return new HtmlResponder($container, $manager);
    }
}
