<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Interfaces;

use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\PluralForm;

interface EntryInterface
{
    public function getContext() :? string;

    /**
     * @return string
     */
    public function getOriginal() : string;

    /**
     * @return ?string
     */
    public function getPlural() : ?string;

    /**
     * @return array{"0":string,"1":string}
     */
    public function getTranslations() : array;

    /**
     * @param int $n
     *
     * @return ?string
     */
    public function getTranslationIndex(int $n = 0) : ?string;

    /**
     * @return string
     */
    public function getId() : string;

    /**
     * @return ?PluralForm
     */
    public function getPluralForm() : ?PluralForm;

    /**
     * @param PluralForm|null $pluralForm
     */
    public function setPluralForm(?PluralForm $pluralForm);
}
