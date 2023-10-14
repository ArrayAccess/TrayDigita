<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets;

use ArrayAccess\TrayDigita\Assets\Abstracts\AbstractDependencies;
use ArrayAccess\TrayDigita\Assets\Dependencies\InlineJavascript;
use ArrayAccess\TrayDigita\Assets\Dependencies\Javascript;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyInlineInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyUriInterface;
use Psr\Http\Message\UriInterface;
use Stringable;

class Js extends AbstractDependencies
{
    protected string $id = 'js';

    public function getId(): string
    {
        return $this->id;
    }

    public function createURL(
        string $id,
        string|UriInterface $source,
        array $attributes = [],
        string ...$inherits
    ): ?DependencyUriInterface {
        return Javascript::create(
            $this,
            $id,
            $source,
            $attributes,
            ...$inherits
        );
    }

    public function createInline(
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ): ?DependencyInlineInterface {
        return InlineJavascript::create(
            $this,
            $id,
            $source,
            $attributes,
            ...$inherits
        );
    }

    public function isSupportInline(): bool
    {
        return true;
    }
}
