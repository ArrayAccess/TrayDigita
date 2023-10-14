<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Interfaces;

use ArrayAccess\TrayDigita\L10n\PoMo\Translation;

interface TranslationFactoryInterface
{
    public function createTranslation(
        ?string $context,
        string $original,
        ?string $plural = null,
        ?string $translation = null,
        string ...$pluralTranslations
    ) : Translation;
}
