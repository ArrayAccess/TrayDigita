<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Adapter\Gettext;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\PoMo\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\L10n\PoMo\Reader\AbstractReader;
use ArrayAccess\TrayDigita\L10n\PoMo\Reader\GettextReader;
use ArrayAccess\TrayDigita\L10n\PoMo\Translations as GettextTranslations;
use ArrayAccess\TrayDigita\L10n\Translations\AbstractAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Entries;
use ArrayAccess\TrayDigita\L10n\Translations\Exceptions\UnsupportedLanguageException;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\AdapterBasedFileInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use function is_dir;
use function is_file;
use function is_readable;
use function pathinfo;
use function realpath;
use function sprintf;
use function substr;
use const PATHINFO_EXTENSION;

class PoMoAdapter extends AbstractAdapter implements AdapterBasedFileInterface
{
    /**
     * @var array<string,array<string, bool>>
     */
    private array $registeredDirectory = [];

    /**
     * @var array<string, string<bool>>
     */
    private array $strict = [];

    /**
     * @var GettextReader
     */
    private GettextReader $reader;

    /**
     * @var array<string,array<string,Entries>>
     */
    private array $translations = [];

    /**
     * Set ignore context
     *
     * @var bool
     */
    private bool $ignoreContext = true;

    public function __construct()
    {
        $this->reader = new GettextReader(new TranslationFactory());
    }

    public function isIgnoreContext(): bool
    {
        return $this->ignoreContext;
    }

    public function setIgnoreContext(bool $ignoreContext): void
    {
        $this->ignoreContext = $ignoreContext;
    }

    public function getName() : string
    {
        return 'Gettext';
    }

    private function addFromReader(
        AbstractReader $reader,
        string $file,
        ?string $fallbackLanguage = null,
        ?string $forceLanguage = null,
        ?string $forceDomain = null
    ): Entries {
        if (!is_file($file)) {
            throw new FileNotFoundException(
                $file
            );
        }

        if ($forceLanguage) {
            if (!($locale = Locale::normalizeLocale($forceLanguage))) {
                throw new UnsupportedLanguageException(
                    sprintf('Language "%s" is invalid or not supported', $fallbackLanguage)
                );
            }
            $forceLanguage = $locale;
        }

        $translations = $reader->fromFile($file);
        $fallbackLanguage = $forceLanguage
            ??$translations->getHeaders()->getLanguage()
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

        $domain  = $forceDomain??$translations->getHeaders()->getDomain()??'default';
        $entries = $this->all($locale, $domain);
        foreach ($translations->getTranslations() as $translation) {
            $entries->add(PoMoTranslation::createFromTranslation($translation));
        }
        $this->translations[$domain][$locale] = $translations;
        return $entries;
    }

    public function addFromMoFile(
        string $file,
        ?string $fallbackLanguage = null,
        ?string $forceLanguage = null,
        ?string $forceDomain = null
    ): Entries {
        return $this->addFromReader(
            $this->reader->getReader($this->reader::MO),
            $file,
            $fallbackLanguage,
            $forceLanguage,
            $forceDomain
        );
    }

    public function addFromPoFile(
        string $file,
        ?string $fallbackLanguage = null,
        ?string $forceLanguage = null,
        ?string $forceDomain = null
    ): Entries {
        return $this->addFromReader(
            $this->reader->getReader($this->reader::PO),
            $file,
            $fallbackLanguage,
            $forceLanguage,
            $forceDomain
        );
    }

    public function addFromFile(
        string $file,
        ?string $fallbackLanguage = null,
        ?string $forceLanguage = null,
        ?string $forceDomain = null
    ): Entries {
        pathinfo($file, PATHINFO_EXTENSION);
        $ext = substr($file, -2);
        if (!$ext || !($reader = $this->reader->getReader($ext))) {
            throw new RuntimeException(
                'Could not detect extension file.'
            );
        }
        return $this->addFromReader(
            $reader,
            $file,
            $fallbackLanguage,
            $forceLanguage,
            $forceDomain
        );
    }

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

    private function scanLanguage(string $domain, string $language) : ?Entries
    {
        $entries = $this->translations[$domain][$language]??null;
        if (!isset($this->registeredDirectory[$domain])) {
            return $entries;
        }

        $translations = null;
        foreach ($this->registeredDirectory[$domain] as $directory => $status) {
            if ($status === true) {
                continue;
            }

            $this->registeredDirectory[$domain][$directory] = true;
            $file = "$directory/$domain-$language.mo";
            $file = !is_file($file) || !is_readable($file)
                ? "$directory/$domain-$language.po"
                : $file;
            if ($domain === TranslatorInterface::DEFAULT_DOMAIN
                && !is_file($file)
                && empty($this->strict[$domain][$directory])
            ) {
                $file = "$directory/$language.mo";
                $file = !is_file($file) || !is_readable($file)
                    ? "$directory/$language.po"
                    : $file;
            }

            $file = !is_file($file) || !is_readable($file)
                ? null
                : $file;
            if (!$file) {
                continue;
            }
            if (!$translations) {
                $translations = new GettextTranslations();
                $translations->setLanguage($language)->setDomain($domain);
            }
            $ext = substr($file, -2);
            if (!($reader = $this->reader->getReader($ext))) {
                continue;
            }
            $translations->merge(...$reader->fromFile($file)->getTranslations());
        }
        if ($translations && $translations->count() > 0) {
            $entries ??= new Entries();
            foreach ($translations->getTranslations() as $translation) {
                if (!$translation) {
                    $translation->setPluralForm(
                        $translations->getHeaders()->getPluralForm()
                    );
                }
                $entries->add(PoMoTranslation::createFromTranslation($translation));
            }
            $this->translations[$domain][$language] = $entries;
        }
        return $entries;
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

    /**
     * @param string $language
     * @param string $original
     * @param string $domain
     * @param ?string $context
     *
     * @return EntryInterface|null
     */
    public function find(
        string $language,
        string $original,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
        ?string $context = null
    ) : ?EntryInterface {
        $translations = $this->getTranslationLanguage($language, $domain);
        $entry = $translations?->entry(self::generateId($context, $original));
        if ($entry === null && $context && $this->isIgnoreContext()) {
            $entry = $translations?->entry(self::generateId(null, $original));
        }
        return $entry??null;
    }

    public function all(string $language, string $domain = TranslatorInterface::DEFAULT_DOMAIN) : Entries
    {
        return $this->getTranslationLanguage($language, $domain)??new Entries();
    }
}
