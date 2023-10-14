<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Attributes;

use Attribute;

// phpcs:disable PSR1.Files.SideEffects
#[Attribute(Attribute::TARGET_CLASS)]
class Group
{
    public function __construct(public readonly string $pattern)
    {
    }

    /**
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }
}
