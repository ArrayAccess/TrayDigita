<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Factory;

use ArrayAccess\TrayDigita\L10n\PoMo\Interfaces\TranslationFactoryInterface;
use ArrayAccess\TrayDigita\L10n\PoMo\Translation;

class TranslationFactory implements TranslationFactoryInterface
{
    /**
     * @param string|null $context
     * @param string $original
     * @param string|null $plural
     * @param string|null $translation
     * @param string ...$pluralTranslations
     *
     * @return Translation
     */
    public function createTranslation(
        ?string $context,
        string $original,
        ?string $plural = null,
        ?string $translation = null,
        string ...$pluralTranslations
    ) : Translation {
        return Translation::create($context, $original, $plural, $translation, ...$pluralTranslations);
    }
}
