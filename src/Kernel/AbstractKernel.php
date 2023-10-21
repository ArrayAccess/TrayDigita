<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Kernel;

use ArrayAccess\TrayDigita\Container\Factory\ContainerFactory;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerFactoryInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\HttpKernel\BaseKernel;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;

abstract class AbstractKernel extends BaseKernel
{
    public readonly ContainerFactoryInterface $containerFactory;

    public function __construct(
        ?ContainerFactoryInterface $containerFactory = null,
        ?string $baseConfigFileName = KernelInterface::BASE_CONFIG_FILE_NAME
    ) {
        $containerFactory ??= new ContainerFactory();
        $this->containerFactory = $containerFactory;
        $container = $containerFactory->createDefault();
        if (!($hasRaw = $container->hasRawService(KernelInterface::class))
            || $container->getRawService(KernelInterface::class) !== $this
        ) {
            if ($hasRaw) {
                $container->remove(KernelInterface::class);
            }
            $container->raw(KernelInterface::class, $this);
        }

        $kernel = ContainerHelper::getNull(
            HttpKernelInterface::class,
            $container
        )??new HttpKernel($this, $container, ContainerHelper::getNull(
            ManagerInterface::class,
            $container
        ));

        // kernel should equal this
        if ($kernel->getKernel() !== $this) {
            throw new RuntimeException(
                'Kernel instance could not contain outside current object'
            );
        }
        parent::__construct($kernel, $baseConfigFileName);
    }
}
