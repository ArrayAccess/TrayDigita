<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Interfaces;

use Psr\Http\Message\UriInterface;

interface DependencyUriInterface extends DependencyInterface
{
    /**
     * @param UriInterface|string $source
     * @return $this
     */
    public function setSource(UriInterface|string $source): static;

    /**
     * @return string|UriInterface
     */
    public function getSource(): string|UriInterface;

    /**
     * @param DependenciesInterface $dependencies
     * @param string $id
     * @param string|UriInterface $source
     * @param array $attributes
     * @param string ...$inherits
     * @return self
     */
    public static function create(
        DependenciesInterface $dependencies,
        string $id,
        string|UriInterface $source,
        array $attributes = [],
        string ...$inherits
    ): self;
}
