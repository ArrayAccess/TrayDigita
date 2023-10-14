<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations;

use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\AdapterInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    public function getName(): string
    {
        return $this::class;
    }

    final public static function generateId(?string $context, string $original) : string
    {
        return Entry::generateId($context, $original);
    }
}
