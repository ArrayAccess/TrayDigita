<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Attributes;

use Attribute;

// phpcs:disable PSR1.Files.SideEffects
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SubscribeEvent
{
}
