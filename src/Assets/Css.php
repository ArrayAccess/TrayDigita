<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets;

use ArrayAccess\TrayDigita\Assets\Abstracts\AbstractDependencies;
use ArrayAccess\TrayDigita\Assets\Dependencies\CascadingStyleSheet;
use ArrayAccess\TrayDigita\Assets\Dependencies\InlineCascadingStyleSheet;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyInlineInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyUriInterface;
use Psr\Http\Message\UriInterface;
use Stringable;

class Css extends AbstractDependencies
{
    protected string $id = 'css';

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
        return CascadingStyleSheet::create(
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
        return InlineCascadingStyleSheet::create(
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
