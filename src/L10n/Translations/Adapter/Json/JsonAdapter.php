<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Adapter\Json;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\Translations\AbstractAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Entries;
use ArrayAccess\TrayDigita\L10n\Translations\Exceptions\UnsupportedLanguageException;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\AdapterBasedFileInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use Throwable;
use function is_dir;
use function is_file;
use function is_readable;
use function realpath;
use function sprintf;

class JsonAdapter extends AbstractAdapter implements AdapterBasedFileInterface
{
    /**
     * @var array<string,array<string, bool>>
     */
    private array $registeredDirectory = [];

    /**
     * @var array<string, string<bool>>
     */
    private array $strict = [];

    private array $translations = [];

    /**
     *
     * @param string $directory
     * @param string $domain
     * @param bool $strict
     * @return bool
     */
    public function registerDirectory(string $directory, string $domain, bool $strict = false) : bool
    {
        if (!is_dir($directory)) {
            return false;
        }
        $directory = realpath($directory)?:DataNormalizer::normalizeDirectorySeparator($directory, true);
        $this->registeredDirectory[$domain][$directory] ??= false;
        $this->strict[$domain][$directory] = $strict;
        return true;
    }

    public function getName(): string
    {
        return 'Json';
    }

    /**
     * @param string $language
     * @param string $domain
     *
     * @return ?Entries
     */
    public function getTranslationLanguage(
        string $language,
        string $domain
    ) : ?Entries {
        $language = Locale::normalizeLocale($language);
        return !$language ? null : $this->scanLanguage($domain, $language);
    }

    private function scanLanguage(string $domain, string $language) : ?Entries
    {
        $entries = $this->translations[$domain][$language]??null;
        if (!isset($this->registeredDirectory[$domain])) {
            return $entries;
        }

        foreach ($this->registeredDirectory[$domain] as $directory => $status) {
            if ($status === true) {
                continue;
            }
            $this->registeredDirectory[$domain][$directory] = true;
            $file = "$directory/$domain-$language.json";
            $file = !is_file($file) || !is_readable($file)
                ? "$directory/$domain-$language.json"
                : $file;
            if ($domain === TranslatorInterface::DEFAULT_DOMAIN
                && !is_file($file)
                && empty($this->strict[$domain][$directory])
            ) {
                $file = "$directory/$language.json";
                $file = !is_file($file) || !is_readable($file)
                    ? "$directory/$language.json"
                    : $file;
            }
            $file = !is_file($file) || !is_readable($file)
                ? null
                : $file;
            if (!$file) {
                continue;
            }
            try {
                $structure = $this->createJsonTranslationFromFile($file);
                if (!$entries) {
                    $entries = $structure->getEntries();
                    continue;
                }
                $entries->merge(...$structure->getEntries()->getEntries());
            } catch (Throwable) {
                continue;
            }
        }

        if ($entries) {
            $this->translations[$domain][$language] ??= $entries;
        }
        return $entries?:null;
    }

    public function createJsonTranslationFromFile(
        string $file
    ): JsonTranslationStructure {
        return JsonTranslationStructure::loadFromFile($file);
    }

    public function addFromFile(
        string $file,
        ?string $fallbackLanguage = null,
        ?string $forceLanguage = null,
        ?string $forceDomain = null
    ) : Entries {
        return $this->addFromJsonTranslation(
            $this->createJsonTranslationFromFile($file),
            $fallbackLanguage,
            $forceLanguage,
            $forceDomain
        );
    }

    public function addFromJsonTranslation(
        JsonTranslationStructure $translation,
        ?string $fallbackLanguage = null,
        ?string $forceLanguage = null,
        ?string $forceDomain = null
    ): Entries {
        if ($forceLanguage) {
            if (!($locale = Locale::normalizeLocale($forceLanguage))) {
                throw new UnsupportedLanguageException(
                    sprintf('Language "%s" is invalid or not supported', $fallbackLanguage)
                );
            }
            $forceLanguage = $locale;
        }
        $fallbackLanguage = $forceLanguage
            ??$translation->getLanguage()
            ??$fallbackLanguage;
        if (!$fallbackLanguage) {
            throw new RuntimeException(
                'Could not detect language.'
            );
        }
        $locale = Locale::normalizeLocale($fallbackLanguage);
        if (!$locale) {
            throw new UnsupportedLanguageException(
                sprintf('Language "%s" is invalid or not supported', $fallbackLanguage)
            );
        }

        $domain  = $forceDomain??$translation->getDomain();
        $translations = $this->all($locale, $domain);
        $translations->merge(...$translation->getEntries()->getEntries());
        $this->translations[$domain][$locale] = $translations;
        return $translations;
    }

    public function find(
        string $language,
        string $original,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
        ?string $context = null
    ): ?EntryInterface {
        return $this
            ->all($language, $domain)
            ->entry(self::generateId($context, $original));
    }

    public function all(string $language, string $domain = TranslatorInterface::DEFAULT_DOMAIN): Entries
    {
        return $this->getTranslationLanguage($language, $domain)?:new Entries();
    }
}
