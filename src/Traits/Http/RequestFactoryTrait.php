<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Http;

use ArrayAccess\TrayDigita\Http\Factory\RequestFactory;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestFactoryInterface;

trait RequestFactoryTrait
{
    abstract public function getContainer() : ?ContainerInterface;

    public function getRequestFactory() : RequestFactoryInterface
    {
        return ContainerHelper::use(
            RequestFactoryInterface::class,
            $this->getContainer()
        )??new RequestFactory();
    }
}
