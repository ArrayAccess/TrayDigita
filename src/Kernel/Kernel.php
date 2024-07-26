<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Kernel;

use ArrayAccess\TrayDigita\Container\Factory\ContainerFactory;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;

class Kernel extends AbstractKernel
{
    public const VERSION = '2.0.2';

    public const NAME = 'TrayDigita';

    final public function __construct(
        ?string $baseConfigFileName = KernelInterface::BASE_CONFIG_FILE_NAME
    ) {
        parent::__construct(new ContainerFactory(), $baseConfigFileName);
    }
}
