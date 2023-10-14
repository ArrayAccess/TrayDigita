<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Interfaces;

interface TranslatorInterface
{
    const DEFAULT_DOMAIN = 'default';

    const DEFAULT_LANGUAGE = 'en';

    const SYSTEM_LANGUAGE = 'en';

    public function getLanguage() : string;

    public function setLanguage(string $language);

    /**
     * @param string $name adapter name
     * @param AdapterInterface $adapter
     */
    public function setAdapter(string $name, AdapterInterface $adapter);

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

    public function translatePlural(
        string $singular,
        string $plural,
        int|float $number,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ) : string;

    public function find(
        string $singular,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ): ?EntryInterface;
}
