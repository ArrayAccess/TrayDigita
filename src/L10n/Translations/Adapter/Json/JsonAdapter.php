<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Adapter\Json;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\Translations\AbstractAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Entries;
use ArrayAccess\TrayDigita\L10n\Translations\Exceptions\UnsupportedLanguageException;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use Throwable;
use function is_dir;
use function is_file;
use function is_readable;
use function is_string;
use function realpath;
use function sprintf;

class JsonAdapter extends AbstractAdapter
{
    /**
     * @var array<string,array<string, bool>> The registered directories
     */
    private array $registeredDirectory = [];

    /**
     * @var array<string, string<bool>> The strict mode
     */
    private array $strict = [];

    /**
     * The translations entries
     *
     * @var array<string,array<string, Entries>>
     */
    private array $translations = [];

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Json';
    }

    /**
     * Get translation language
     *
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

    /**
     * Scan language from domain and language
     *
     * @param string $domain
     * @param string $language
     * @return ?Entries The entries or null
     */
    private function scanLanguage(string $domain, string $language) : ?Entries
    {
        $entries     = $this->translations[$domain][$language]??null;
        $directories = $this->translator->getRegisteredDirectories()[$domain]??[];
        foreach ($directories as $directory) {
            if (!is_string($directory) || !is_dir($directory)) {
                continue;
            }
            $directory = realpath($directory);
            if (!empty($this->registeredDirectory[$domain][$directory][$language])) {
                continue;
            }
            $this->registeredDirectory[$domain][$directory][$language] = true;
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

    /**
     * Create json translation from file
     *
     * @param string $file The file
     * @return JsonTranslationStructure
     */
    public function createJsonTranslationFromFile(
        string $file
    ): JsonTranslationStructure {
        return JsonTranslationStructure::loadFromFile($file);
    }

    /**
     * Add translation from file
     *
     * @param string $file The file
     * @param string|null $fallbackLanguage
     * @param string|null $forceLanguage
     * @param string|null $forceDomain
     * @return Entries
     */
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

    /**
     * Add translation from json translation
     *
     * @param JsonTranslationStructure $translation
     * @param string|null $fallbackLanguage
     * @param string|null $forceLanguage
     * @param string|null $forceDomain
     * @return Entries
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public function all(string $language, string $domain = TranslatorInterface::DEFAULT_DOMAIN): Entries
    {
        return $this->getTranslationLanguage($language, $domain)?:new Entries();
    }
}
