<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Interfaces;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\ObjectMismatchException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use Psr\Http\Message\UriInterface;
use Stringable;

interface DependenciesInterface
{
    public function __construct(AssetsCollectionInterface $packages);

    public function getAssetsCollection(): AssetsCollectionInterface;

    public function getId() : string;

    /**
     * @param DependencyInterface $dependency
     * @return bool
     * @throws ObjectMismatchException|UnsupportedArgumentException
     */
    public function register(DependencyInterface $dependency) : bool;

    public function deregister(DependencyInterface|string $dependency) : ?DependencyInterface;

    public function isSupportInline() : bool;

    public function registerURL(
        string $id,
        string|UriInterface $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyUriInterface;

    public function registerInline(
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyInlineInterface;

    public function createURL(
        string $id,
        string|UriInterface $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyUriInterface;

    public function createInline(
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ): ?DependencyInlineInterface;

    public function has(string $id) : bool;

    public function get(string $id): ?DependencyInterface;

    public function getDependencies() : array;

    public function getRenderCollection(string $id);

    public function render(string $id, bool $skipRendered = false) : string;

    public function isRendered(string $id) : bool;
}
