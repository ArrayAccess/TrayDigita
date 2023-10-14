<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Http;

use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamFactoryInterface;

trait StreamFactoryTrait
{
    abstract public function getContainer() : ?ContainerInterface;

    public function getStreamFactory() : StreamFactoryInterface
    {
        return ContainerHelper::use(
            StreamFactoryInterface::class,
            $this->getContainer()
        )??new StreamFactory();
    }
}
