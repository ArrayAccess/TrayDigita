<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
