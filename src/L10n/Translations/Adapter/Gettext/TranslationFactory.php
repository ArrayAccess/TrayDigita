<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Adapter\Gettext;

use ArrayAccess\TrayDigita\L10n\PoMo\Interfaces\TranslationFactoryInterface;
use ArrayAccess\TrayDigita\L10n\PoMo\Translation as PoMoTranslation;

class TranslationFactory implements TranslationFactoryInterface
{
    /**
     * @param string|null $context
     * @param string $original
     * @param string|null $plural
     * @param string|null $translation
     * @param string ...$pluralTranslations
     *
     * @return PoMoTranslation
     */
    public function createTranslation(
        ?string $context,
        string $original,
        ?string $plural = null,
        ?string $translation = null,
        string ...$pluralTranslations
    ) : PoMoTranslation {
        return PoMoTranslation::create(
            $context,
            $original,
            $plural,
            $translation,
            ...$pluralTranslations
        );
    }
}
