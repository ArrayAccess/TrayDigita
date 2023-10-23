<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations;

use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\AdapterInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    public function getName(): string
    {
        return $this::class;
    }

    final public static function generateId(?string $context, string $original) : string
    {
        return Entry::generateId($context, $original);
    }
}
