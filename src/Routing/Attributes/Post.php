<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Attributes;

use Attribute;

// phpcs:disable PSR1.Files.SideEffects
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Post extends Abstracts\HttpMethodAttributeAbstract
{
}
