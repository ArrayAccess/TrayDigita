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

    /**
     * Register a directory to search for controllers
     *
     * @param string ...$directory The directory to search for controllers
     */
    public function registerControllerDirectory(string ...$directory);

    /**
     * Remove a directory from search for controllers
     *
     * @param string ...$directory The directory to remove from search for controllers
     */
    public function removeControllerDirectory(string ...$directory);

    /**
     * Get start memory
     *
     * @return int Start memory
     */
    public function getStartMemory() : int;

    /**
     * Get start time
     *
     * @return float Start time
     * @uses \microtime()
     */
    public function getStartTime() : float;

    /**
     * Boot the kernel
     */
    public function boot();

    /**
     * Shutdown the kernel
     */
    public function shutdown();

    /**
     * Check if the kernel is booted
     *
     * @return bool True if the kernel is booted, false otherwise
     */
    public function isBooted(): bool;

    /**
     * Check if the kernel is shutdown
     *
     * @return bool True if the kernel is shutdown, false otherwise
     */
    public function isShutdown(): bool;

    /**
     * Get the Http Kernel
     *
     * @return HttpKernelInterface
     */
    public function getHttpKernel() : HttpKernelInterface;

    /**
     * Get the config error
     *
     * @return ?string The config
     * @uses KernelInterface::CONFIG_EMPTY_FILE
     * @uses KernelInterface::CONFIG_NOT_FILE
     * @uses KernelInterface::CONFIG_NOT_ITERABLE
     * @uses KernelInterface::CONFIG_UNAVAILABLE
     */
    public function getConfigError(): ?string;

    /**
     * Get the config file
     *
     * @return ?string The config file
     */
    public function getConfigFile(): ?string;

    /**
     * Get the root directory
     *
     * @return ?string The root directory
     */
    public function getRootDirectory(): ?string;

    /**
     * Check if the kernel has been initialized
     *
     * @return bool
     */
    public function isHasInit(): bool;

    /**
     * Initialize the kernel
     *
     * @return $this
     */
    public function init() : static;

    /**
     * Check if the kernel is ready
     *
     * @return bool
     */
    public function isReady() : bool;
}
