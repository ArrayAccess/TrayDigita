<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Abstracts;

use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyInterface;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes;
use function array_key_exists;
use function is_scalar;
use function strtolower;
use function trim;

abstract class AbstractDependency implements DependencyInterface
{
    /**
     * @var string
     */
    protected string $id;

    /**
     * @var array<string>
     */
    protected array $inherits = [];

    /**
     * @var array
     */
    protected array $attributes = [];

    public function __construct(public readonly DependenciesInterface $dependencies)
    {
    }

    public function getDependencies(): DependenciesInterface
    {
        return $this->dependencies;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getInherits(): array
    {
        return $this->inherits;
    }

    public function getAttributes() : array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name): bool
    {
        $key = HtmlAttributes::HTML_ATTRIBUTES[strtolower(trim($name))]??$name;
        return array_key_exists($key, $this->attributes);
    }

    public function removeAttribute(string $name): static
    {
        $key = HtmlAttributes::HTML_ATTRIBUTES[strtolower(trim($name))]??$name;
        unset($this->attributes[$key]);
        return $this;
    }

    public function setAttribute(
        string $name,
        $value
    ): static {
        if ($value !== null && !is_scalar($value)) {
            return $this;
        }
        $key = HtmlAttributes::HTML_ATTRIBUTES[strtolower(trim($name))]??$name;
        $this->attributes[$key] = $value;
        return $this;
    }

    abstract public function render(): string;

    public function __toString(): string
    {
        return $this->render();
    }
}
