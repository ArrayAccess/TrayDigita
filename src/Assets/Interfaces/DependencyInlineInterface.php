<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Interfaces;

use Stringable;

interface DependencyInlineInterface extends DependencyInterface
{
    /**
     * @param Stringable|string $source
     * @return $this
     */
    public function setSource(Stringable|string $source): static;

    /**
     * @return string|Stringable
     */
    public function getSource(): string|Stringable;

    /**
     * @param DependenciesInterface $dependencies
     * @param string $id
     * @param string|Stringable $source
     * @param array $attributes
     * @param string ...$inherits
     * @return self
     */
    public static function create(
        DependenciesInterface $dependencies,
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ): self;
}
