<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Interfaces;

use Psr\Http\Message\UriInterface;
use Stringable;

/**
 * @template ObjectMismatchException of \ArrayAccess\TrayDigita\Exceptions\InvalidArgument\ObjectMismatchException
 * @template Unsupported of \ArrayAccess\TrayDigita\Exceptions\InvalidArgument\Unsupported
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
interface DependenciesInterface
{
    /**
     * DependenciesInterface constructor.
     *
     * @param AssetsCollectionInterface $packages The assets collection
     */
    public function __construct(AssetsCollectionInterface $packages);

    /**
     * Get the assets collection
     *
     * @return AssetsCollectionInterface The assets collection
     */
    public function getAssetsCollection(): AssetsCollectionInterface;

    /**
     * Get the id of the dependencies
     *
     * @return string The id of the dependencies
     */
    public function getId() : string;

    /**
     * @param DependencyInterface $dependency
     * @return bool
     * @throws ObjectMismatchException|Unsupported
     */
    public function register(DependencyInterface $dependency) : bool;

    /**
     * Deregister a dependency
     *
     * @param DependencyInterface|string $dependency
     * @return ?DependencyInterface The dependency that deregistered
     */
    public function deregister(DependencyInterface|string $dependency) : ?DependencyInterface;

    /**
     * Check if the dependencies is support inline
     * @return bool True if the dependencies is support inline
     */
    public function isSupportInline() : bool;

    /**
     * Register a dependency by URL
     *
     * @param string $id
     * @param string|UriInterface $source
     * @param array $attributes
     * @param string ...$inherits
     * @return DependencyUriInterface|null
     */
    public function registerURL(
        string $id,
        string|UriInterface $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyUriInterface;

    /**
     * Register inline dependency
     *
     * @param string $id
     * @param string|Stringable $source
     * @param array $attributes
     * @param string ...$inherits
     * @return DependencyInlineInterface|null
     */
    public function registerInline(
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyInlineInterface;

    /**
     * Create URL dependency
     *
     * @param string $id
     * @param string|UriInterface $source
     * @param array $attributes
     * @param string ...$inherits
     * @return DependencyUriInterface|null
     */
    public function createURL(
        string $id,
        string|UriInterface $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyUriInterface;

    /**
     * Create inline dependency
     *
     * @param string $id
     * @param string|Stringable $source
     * @param array $attributes
     * @param string ...$inherits
     * @return DependencyInlineInterface|null
     */
    public function createInline(
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ): ?DependencyInlineInterface;

    /**
     * Check if the dependencies has the dependency
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id) : bool;

    /**
     * Get the dependency
     *
     * @param string $id
     * @return ?DependencyInterface The dependency
     */
    public function get(string $id): ?DependencyInterface;

    /**
     * Get the dependencies
     *
     * @return array<string, DependencyInterface> The dependencies
     */
    public function getDependencies() : array;

    /**
     * Get the dependencies collection by id
     *
     * @param string $id
     */
    public function getRenderCollection(string $id);

    /**
     * Render the dependencies by id
     *
     * @param string $id The id of the dependencies
     * @param bool $skipRendered Skip rendered dependencies
     * @return string The rendered content
     */
    public function render(string $id, bool $skipRendered = false) : string;

    /**
     * Check if the dependencies is rendered
     *
     * @param string $id
     * @return bool
     */
    public function isRendered(string $id) : bool;
}
