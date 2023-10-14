<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Responder;

use ArrayAccess\TrayDigita\Responder\Factory\HtmlResponderFactory;
use ArrayAccess\TrayDigita\Responder\Interfaces\HtmlResponderFactoryInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\HtmlResponderInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;

trait HtmlResponderFactoryTrait
{
    abstract public function getContainer() : ?ContainerInterface;

    protected function getHtmlResponder() : HtmlResponderInterface
    {
        $container = $this->getContainer();
        return ContainerHelper::getNull(
            HtmlResponderInterface::class,
            $container
        )??$this->getHtmlResponderFactory()->createHtmlResponder(
            $container
        );
    }

    protected function getHtmlResponderFactory() : HtmlResponderFactory
    {
        return ContainerHelper::getNull(
            HtmlResponderFactoryInterface::class,
            $this->getContainer()
        )??new HtmlResponderFactory();
    }
}
