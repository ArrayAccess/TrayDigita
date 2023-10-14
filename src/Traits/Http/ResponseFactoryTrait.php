<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Http;

use ArrayAccess\TrayDigita\Http\Factory\ResponseFactory;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

trait ResponseFactoryTrait
{
    abstract public function getContainer() : ?ContainerInterface;

    public function getResponseFactory() : ResponseFactoryInterface
    {
        return ContainerHelper::use(
            ResponseFactoryInterface::class,
            $this->getContainer()
        )??new ResponseFactory();
    }
}
