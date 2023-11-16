<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Interfaces;

interface TranslatorInterface
{
    public const DEFAULT_DOMAIN = 'default';

    public const DEFAULT_LANGUAGE = 'en';

    public const SYSTEM_LANGUAGE = 'en';

    public function getLanguage() : string;

    public function setLanguage(string $language);

    /**
     * @param string $name adapter name
     * @param AdapterInterface $adapter
     */
    public function setAdapter(string $name, AdapterInterface $adapter);

    /**
     * @param string $domain
     * @param string ...$directory
     * @return bool
     */
    public function registerDirectory(string $domain, string ...$directory) : bool;

    /**
     * @return array<string, string[]>
     */
    public function getRegisteredDirectories() : array;

    /**
     * @param AdapterInterface $adapter
     * @param string|null $name
     * @return bool true if successfully registered
     */
    public function addAdapter(AdapterInterface $adapter, ?string $name = null) : bool;

    /**
     * @param AdapterInterface|class-string<AdapterInterface> ...$adapters
     * @return array removed adapter
     */
    public function removeAdapter(AdapterInterface|string ...$adapters) : array;

    /**
     * @return array<string, AdapterInterface>
     */
    public function getAdapters() : array;

    public function translate(
        string $singular,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ) : string;

    public function translateContext(
        string $singular,
        string $context,
        string $domain = self::DEFAULT_DOMAIN,
    ) : string;

    /**
     * Translate plural
     *
     * @param string $singular
     * @param string $plural
     * @param int|float $number
     * @param string $domain
     * @param string|null $context
     * @return string
     */
    public function translatePlural(
        string $singular,
        string $plural,
        int|float $number,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ) : string;

    /**
     * Translate plural context
     *
     * @param string $singular
     * @param string $plural
     * @param int|float $number
     * @param string $context
     * @param string $domain
     * @return string
     */
    public function translatePluralContext(
        string $singular,
        string $plural,
        int|float $number,
        string $context,
        string $domain = self::DEFAULT_DOMAIN
    ) : string;

    /**
     * Find translation
     *
     * @param string $singular
     * @param string $domain
     * @param string|null $context
     * @return EntryInterface|null
     */
    public function find(
        string $singular,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ): ?EntryInterface;
}
