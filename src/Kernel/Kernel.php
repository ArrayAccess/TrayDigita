<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Kernel;

use ArrayAccess\TrayDigita\Container\Factory\ContainerFactory;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;

class Kernel extends AbstractKernel
{
    const VERSION = '1.0.0';
    const NAME = 'TrayDigita';

    final public function __construct(
        ?string $baseConfigFileName = KernelInterface::BASE_CONFIG_FILE_NAME
    ) {
        parent::__construct(new ContainerFactory(), $baseConfigFileName);
    }
}
