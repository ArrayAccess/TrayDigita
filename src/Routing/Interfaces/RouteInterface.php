<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Interfaces;

interface RouteInterface
{
    public const DEFAULT_PRIORITY = 10;

    /**
     * @param array<string>|string $methods route methods
     * @param string $pattern route pattern
     * @param callable|array $controller route callback or controller
     * @param ?int $priority route priority
     * @param ?string $name route name
     * @param ?string $hostName route hostname
     */
    public function __construct(
        array|string $methods,
        string $pattern,
        callable|array $controller,
        ?int $priority = self::DEFAULT_PRIORITY,
        ?string $name = null,
        ?string $hostName = null
    );

    /**
     * Get route pattern
     *
     * @return string
     */
    public function getPattern(): string;

    /**
     * Get compiled route pattern
     *
     * @return string
     */
    public function getCompiledPattern(): string;

    /**
     * The route name
     *
     * @return ?string
     */
    public function getName(): ?string;

    /**
     * Set route name
     *
     * @param ?string $name
     * @return $this
     */
    public function setName(?string $name) : static;

    /**
     * Set route priority, smallest int will the highest priority
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Set route priority
     *
     * @param int $priority
     * @return mixed
     */
    public function setPriority(int $priority) : static;

    /**
     * @return ?string
     */
    public function getHostName(): ?string;

    /**
     * Set matched hostname, maybe should regex support
     * @see \ArrayAccess\TrayDigita\Routing\Router::REGEX_DELIMITER for support delimiter
     *
     * @param string|null $hostName
     * @return $this
     */
    public function setHostName(?string $hostName) : static;

    /**
     * Set route callback
     *
     * @param callable $callback
     * @return $this
     */
    public function setHandler(callable $callback) : static;

    /**
     * Get route methods
     *
     * @return array
     */
    public function getMethods(): array;

    /**
     * @return callable|array
     */
    public function getCallback(): callable|array;

    /**
     * Set controller on current route
     *
     * @param class-string<ControllerInterface>|ControllerInterface $controller
     * @param string $method
     * @return $this
     */
    public function setController(
        string|ControllerInterface $controller,
        string $method
    ) : static;

    /**
     * Check if route contains method
     *
     * @param string $method
     * @return bool
     */
    public function containMethod(string $method): bool;
}
