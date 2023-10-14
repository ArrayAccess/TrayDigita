<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Attributes;

use Attribute;

// phpcs:disable PSR1.Files.SideEffects
#[Attribute(Attribute::TARGET_METHOD)]
/**
 * ALL determine all request include "*"
 */
final readonly class All extends Abstracts\HttpMethodAttributeAbstract
{
}
