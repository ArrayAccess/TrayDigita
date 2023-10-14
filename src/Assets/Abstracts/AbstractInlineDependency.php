<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Abstracts;

use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyInlineInterface;
use Psr\Http\Message\UriInterface;
use Stringable;
use function array_filter;
use function array_unique;
use function is_string;

abstract class AbstractInlineDependency extends AbstractDependency implements DependencyInlineInterface
{
    /**
     * @var string|UriInterface
     */
    protected UriInterface|string $source;

    public function setSource(Stringable|string $source): static
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @return Stringable|string
     */
    public function getSource(): Stringable|string
    {
        return $this->source;
    }

    public static function create(
        DependenciesInterface $dependencies,
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ): static {
        $object = new static($dependencies);
        $object->id = $id;
        foreach ($attributes as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $object->setAttribute($key, $value);
        }
        $object->setSource($source);
        $inherits = array_filter($inherits, 'is_string');
        $object->inherits = array_filter(array_unique($inherits));
        return $object;
    }
}
