<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Kernel;

use ArrayAccess\TrayDigita\Container\Factory\ContainerFactory;
use ArrayAccess\TrayDigita\HttpKernel\BaseKernel;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;

abstract class AbstractKernel extends BaseKernel
{
    public readonly ContainerFactory $containerFactory;

    public function __construct(
        ?ContainerFactory $containerFactory = null,
        ?string $baseConfigFileName = KernelInterface::BASE_CONFIG_FILE_NAME
    ) {
        $containerFactory ??= new ContainerFactory();
        $this->containerFactory = $containerFactory;
        $container = $containerFactory->createDefault();
        if (!$container->has(HttpKernelInterface::class)) {
            $container->set(HttpKernelInterface::class, HttpKernel::class);
        }
        $kernel = $container->get(HttpKernelInterface::class);
        parent::__construct($kernel, $baseConfigFileName);
    }
}
