<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Responder;

use ArrayAccess\TrayDigita\Responder\Factory\JsonResponderFactory;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonResponderFactoryInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonResponderInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;

trait JsonResponderFactoryTrait
{
    abstract public function getContainer() : ?ContainerInterface;

    protected function getJsonResponder() : JsonResponderInterface
    {
        $container = $this->getContainer();
        return ContainerHelper::getNull(
            JsonResponderInterface::class,
            $container
        )??$this->getJsonResponderFactory()->createJsonResponder(
            $container
        );
    }

    /**
     * @return JsonResponderFactory
     */
    protected function getJsonResponderFactory() : JsonResponderFactory
    {
        return ContainerHelper::use(
            JsonResponderFactoryInterface::class,
            $this->getContainer()
        )??new JsonResponderFactory();
    }
}
