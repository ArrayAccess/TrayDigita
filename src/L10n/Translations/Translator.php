<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\Translations\Exceptions\UnsupportedLanguageException;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\AdapterInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use Throwable;
use function is_array;
use function is_float;
use function is_int;
use function is_object;
use function round;
use function spl_object_hash;
use function sprintf;

class Translator implements TranslatorInterface, ManagerAllocatorInterface
{
    use ManagerAllocatorTrait,
        ManagerDispatcherTrait;

    /**
     * @var string
     */
    private string $systemLanguage;

    /**
     * @var string
     */
    private string $language;

    /**
     * @var array<string, AdapterInterface>
     */
    private array $adapters = [];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $adapterHash = [];

    /**
     * @param string $language
     * @param string $systemLanguage
     * @param ManagerInterface|null $manager
     */
    public function __construct(
        string $language = self::DEFAULT_LANGUAGE,
        string $systemLanguage = self::SYSTEM_LANGUAGE,
        ?ManagerInterface $manager = null
    ) {
        $this->setLanguage($language);
        $this->setSystemLanguage($systemLanguage?:self::SYSTEM_LANGUAGE);
        $manager && $this->setManager($manager);
    }

    public function isNeedTranslated() : bool
    {
        return $this->language !== $this->systemLanguage;
    }

    /**
     * @return string
     */
    public function getLanguage() : string
    {
        return $this->language;
    }

    /**
     * @return string
     */
    public function getSystemLanguage() : string
    {
        return $this->systemLanguage;
    }

    /**
     * @param string $language
     */
    public function setSystemLanguage(string $language) : void
    {
        $originalLanguage = $language;
        $language = Locale::normalizeLocale($language);
        if (!$language) {
            throw new UnsupportedLanguageException(
                sprintf('Language "%s" is invalid or not supported', $originalLanguage)
            );
        }
        $this->systemLanguage = $language;
    }

    /**
     * @param string $language
     */
    public function setLanguage(string $language) : void
    {
        $originalLanguage = $language;
        $language = Locale::normalizeLocale($language);
        if (!$language) {
            throw new UnsupportedLanguageException(
                sprintf('Language "%s" is invalid or not supported', $originalLanguage)
            );
        }
        $this->language = $language;
    }

    public function setAdapter(string $name, AdapterInterface $adapter) : void
    {
        // ::class;
        $adapterHash = $this->adapterHash;
        foreach ($adapterHash as $hash => $booleans) {
            if (!isset($booleans[$name])) {
                continue;
            }
            unset($this->adapters[$hash][$name]);
            if (empty($this->adapters[$hash])) {
                unset($this->adapters[$hash]);
            }
        }
        $hash = spl_object_hash($adapter);
        $this->adapters[$name] = $adapter;
        $this->adapterHash[$hash][$name] = true;
    }

    public function addAdapter(
        AdapterInterface $adapter,
        ?string $name = null
    ) : bool {
        $name ??= $adapter::class;
        if ($this->hasAdapter($name)) {
            return false;
        }
        $this->setAdapter($name, $adapter);
        return true;
    }

    public function removeAdapter(string|AdapterInterface ...$adapters) : array
    {
        $removed = [];
        foreach ($adapters as $adapter) {
            if (is_object($adapter)) {
                $hash = spl_object_hash($adapter);
                $adapterLists = ($this->adapterHash[$hash]??null);
                if ($adapterLists !== null) {
                    unset($this->adapterHash[$hash]);
                    foreach ($adapterLists as $name => $true) {
                        if (!isset($this->adapters[$name])) {
                            continue;
                        }
                        $removed[$name]= $this->adapters[$name];
                        unset($this->adapters[$name]);
                    }
                }
            } elseif (isset($this->adapters[$adapter])) {
                $hash = spl_object_hash($this->adapters[$adapter]);
                $removed[$adapter] = $this->adapters[$adapter];
                unset($this->adapters[$adapter]);
                if (!isset($this->adapterHash[$hash])) {
                    continue;
                }
                if (!is_array($this->adapterHash[$hash])) {
                    unset($this->adapterHash[$hash]);
                    continue;
                }
                if (isset($this->adapterHash[$hash][$adapter])) {
                    unset($this->adapterHash[$hash][$adapter]);
                }
                if (empty($this->adapterHash[$hash])) {
                    unset($this->adapterHash[$hash]);
                }
            }
        }

        return $removed;
    }

    public function hasAdapter(AdapterInterface|string $adapter): bool
    {
        if (is_object($adapter)) {
            $hash = spl_object_hash($adapter);
            $adapterLists = $this->adapterHash[$hash] ?? [];
            if (!is_array($adapterLists)) {
                unset($this->adapterHash[$hash]);
                return false;
            }
            if (empty($adapterLists)) {
                return false;
            }
            foreach ($adapterLists as $adapterName => $true) {
                if (isset($this->adapters[$adapterName])) {
                    return true;
                }
            }
            unset($this->adapterHash[$hash]);
            return false;
        }
        return isset($this->adapters[$adapter]);
    }

    public function getAdapter(AdapterInterface|string $adapter): ?AdapterInterface
    {
        if (is_object($adapter)) {
            $hash = spl_object_hash($adapter);
            $adapterLists = $this->adapterHash[$hash] ?? [];
            if (!is_array($adapterLists)) {
                unset($this->adapterHash[$hash]);
                return null;
            }
            foreach ($adapterLists as $adapterName => $true) {
                if (isset($this->adapters[$adapterName])) {
                    return $this->adapters[$adapterName];
                }
            }
            unset($this->adapterHash[$hash]);
            return null;
        }
        return $this->adapters[$adapter]??null;
    }

    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * @param int $count
     * @param EntryInterface $entry
     * @return int
     */
    public function selectPluralIndex(
        int $count,
        EntryInterface $entry
    ) : int {
        $defaultIndex = (1 === $count ? 0 : 1);
        // @dispatch(translator.pluralIndex)
        $index = $this->getManager()?->dispatch(
            'translator.pluralIndex',
            $defaultIndex,
            $count,
            $entry,
            $this
        );
        return is_int($index) ? $index : $defaultIndex;
    }

    /**
     * @param string $singular
     * @param string $domain
     * @param ?string $context
     *
     * @return string
     */
    public function translate(
        string $singular,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ) : string {
        if (!$this->isNeedTranslated() || empty($this->adapters)) {
            return $singular;
        }
        $translation = $this->find($singular, $domain, $context);
        return $translation?->getTranslationIndex()??$singular;
    }

    /**
     * @param string $singular
     * @param string $context
     * @param string $domain
     * @return string
     */
    public function translateContext(
        string $singular,
        string $context,
        string $domain = self::DEFAULT_DOMAIN,
    ) : string {
        return $this->translate($singular, $domain, $context);
    }

    /**
     * @param string $singular
     * @param string $domain
     * @param string|null $context
     *
     * @return ?Interfaces\EntryInterface
     */
    public function find(
        string $singular,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ): ?Interfaces\EntryInterface {
        $translation = null;
        $manager = $this->getManager();
        $manager?->dispatch(
            'translator.beforeFind',
            $singular,
            $domain,
            $context,
            $this
        );
        $currentAdapter = null;
        try {
            foreach ($this->getAdapters() as $adapter) {
                $translated = $adapter->find(
                    $this->getLanguage(),
                    $singular,
                    $domain,
                    $context
                );
                if ($translated !== null) {
                    $translation = $translated;
                    $currentAdapter = $adapter;
                    $manager?->dispatch(
                        'translator.find',
                        $singular,
                        $domain,
                        $context,
                        $this,
                        $translation,
                        $currentAdapter
                    );
                    break;
                }
            }
            return $translation;
        } finally {
            $manager?->dispatch(
                'translator.afterFind',
                $singular,
                $domain,
                $context,
                $this,
                $translation,
                $currentAdapter
            );
        }
    }

    /**
     * @param string $singular
     * @param string $plural
     * @param int|float $number
     * @param string $domain
     * @param ?string $context
     *
     * @return string
     */
    public function translatePlural(
        string $singular,
        string $plural,
        int|float $number,
        string $domain = self::DEFAULT_DOMAIN,
        ?string $context = null
    ) : string {
        $entry = $this->find($singular, $domain, $context);
        if (!$entry) {
            return $number === 1 ? $singular : $plural;
        }
        $number = is_float($number) ? (int) round($number) : $number;
        $defaultIndex = $this->selectPluralIndex($number, $entry);
        $index = $defaultIndex;
        try {
            $index = $entry->getPluralForm()?->execute($number)??$index;
        } catch (Throwable) {
        }

        $translation = $entry->getTranslationIndex($index);
        return $translation ?? (
            $index === 0 ? $singular : $plural
        );
    }

    /**
     * Translate plural by context
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
    ) : string {
        return $this->translatePlural($singular, $plural, $number, $domain, $context);
    }
}
