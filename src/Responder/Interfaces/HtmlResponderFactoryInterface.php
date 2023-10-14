<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Interfaces;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use Psr\Container\ContainerInterface;

interface HtmlResponderFactoryInterface
{
    public function createHtmlResponder(
        ContainerInterface $container = null,
        ManagerInterface $manager = null
    ) : HtmlResponderInterface;
}
