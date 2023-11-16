<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Kernel\Interfaces;

use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\RunnableInterface;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\TerminableInterface;

interface KernelInterface extends RunnableInterface, TerminableInterface
{
    public const CONFIG_UNAVAILABLE = 'UNAVAILABLE';

    public const CONFIG_NOT_FILE = 'NOT_FILE';

    public const CONFIG_NOT_ITERABLE = 'NOT_ITERABLE';

    public const CONFIG_EMPTY_FILE = 'EMPTY_FILE';

    /**
     * Base config file name
     */
    public const BASE_CONFIG_FILE_NAME = 'config.php';

    public function registerControllerDirectory(string ...$directory);

    public function removeControllerDirectory(string ...$directory);

    public function getStartMemory() : int;

    public function getStartTime() : float;

    public function boot();

    public function shutdown();

    public function isBooted(): bool;

    public function isShutdown(): bool;

    public function getHttpKernel() : HttpKernelInterface;

    public function getConfigError(): ?string;

    public function getConfigFile(): ?string;

    public function getRootDirectory(): ?string;

    public function isHasInit(): bool;

    public function init() : static;

    public function isReady() : bool;
}
