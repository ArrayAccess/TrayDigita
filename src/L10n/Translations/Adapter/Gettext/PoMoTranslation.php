<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Adapter\Gettext;

use ArrayAccess\TrayDigita\L10n\PoMo\Translation as TranslationGettext;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use function array_shift;

class PoMoTranslation extends TranslationGettext implements EntryInterface
{
    /**
     * @param TranslationGettext|EntryInterface $translation
     *
     * @return PoMoTranslation
     */
    public static function createFromTranslation(
        TranslationGettext|EntryInterface $translation
    ) : PoMoTranslation {
        if ($translation instanceof PoMoTranslation) {
            return $translation;
        }

        if ($translation instanceof TranslationGettext) {
            $trans = static::create(
                $translation->getContext(),
                $translation->getOriginal(),
                $translation->getPlural(),
                $translation->getTranslation(),
                ...$translation->getPluralTranslations()
            )->setEnable($translation->isEnable());
            $trans->setPluralForm($translation->getPluralForm());
            return $trans;
        }

        $translations = $translation->getTranslations();
        $trans = static::create(
            $translation->getContext(),
            $translation->getOriginal(),
            $translation->getPlural(),
            array_shift($translations)?:null,
            ...$translations
        );
        $trans->setPluralForm($translation->getPluralForm());
        return $trans;
    }

    public function getTranslations() : array
    {
        return [$this->getTranslation(), ...$this->getPluralTranslations()];
    }

    public function getTranslationIndex(int $n = 0) : ?string
    {
        if ($n === 0) {
            return $this->getTranslation();
        }
        return $this->pluralTranslations[$n - 1]??null;
    }
}
