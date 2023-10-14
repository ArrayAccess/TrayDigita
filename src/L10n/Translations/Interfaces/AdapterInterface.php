<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Interfaces;

use ArrayAccess\TrayDigita\L10n\Translations\Entries;

interface AdapterInterface
{
    public function getName() : string;

    /**
     * @param ?string $context
     * @param string $original
     *
     * @return string
     */
    public static function generateId(?string $context, string $original) : string;

    /**
     * Returning translations
     *
     * @param string $language
     * @param string $original
     * @param string $domain
     * @param string|null $context
     *
     * @return ?EntryInterface
     */
    public function find(
        string $language,
        string $original,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
        ?string $context = null
    ) : ?EntryInterface;

    /**
     * @param string $language
     * @param string $domain
     *
     * @return Entries
     */
    public function all(string $language, string $domain = TranslatorInterface::DEFAULT_DOMAIN) : Entries;
}
