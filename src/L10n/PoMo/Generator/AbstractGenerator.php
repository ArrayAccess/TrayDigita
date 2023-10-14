<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Generator;

use ArrayAccess\TrayDigita\L10n\PoMo\Translations;

abstract class AbstractGenerator
{
    /**
     * @param Translations $translations
     */
    public function __construct(protected Translations $translations)
    {
    }

    /**
     * @return Translations
     */
    public function getTranslations() : Translations
    {
        return $this->translations;
    }

    abstract public function generate() : string;
}
