<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Adapter\Json;

use ArrayAccess\TrayDigita\Exceptions\Logical\OutOfRangeException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\L10n\Exceptions\InvalidDataException;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\PoMo\Helper\PluralParser;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\PluralForm;
use ArrayAccess\TrayDigita\L10n\Translations\Adapter\Json\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\L10n\Translations\Entries;
use ArrayAccess\TrayDigita\L10n\Translations\Entry;
use DateTimeImmutable;
use DateTimeInterface;
use stdClass;
use Throwable;
use function array_change_key_case;
use function array_values;
use function fclose;
use function feof;
use function filesize;
use function fopen;
use function fread;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function sprintf;

class JsonTranslationStructure
{
    /**
     * maximum 4MB
     */
    public const MAX_FILE_SIZE = 4194304;

    public const DEFAULT_DOMAIN = 'default';

    public const LANGUAGE_KEY = 'lang';

    public const REVISION_KEY = 'translation-revision-date';

    public const GENERATOR_KEY = 'generator';

    public const LOCALE_DATA_KEY = 'locale_data';

    public const MESSAGES_KEY = 'messages';

    public const DOMAIN_KEY = 'domain';

    public const PLURAL_FORMS_KEY = 'plural-forms';

    public const VERSION_KEY = 'version';

    public const CREATION_DATE_KEY = 'creation-date';

    public const COMMENT_KEY = 'comment';

    public const REFERENCE_KEY = 'reference';

    public const SCHEMA = [
        "translation-revision-date" => "string",
        "generator" => "string",
        "locale_data" => [
            "messages" => [
                "" => [
                    "domain" => "string",
                    "plural-forms" => "string",
                    "lang" => "string",
                    "version" => "string",
                    "creation-date" => "string",
                ],
                "string" => "array"
            ],
        ],
        "comment" => [
            "reference" => "string",
            "string" => "string"
        ],
    ];

    protected array $metadata = [];
    protected string $domain = self::DEFAULT_DOMAIN;

    protected ?string $language = null;

    protected ?string $generator = null;
    protected ?PluralForm $pluralForms = null;
    protected ?DateTimeInterface $creationDate = null;
    protected ?DateTimeInterface $revisionDate = null;

    protected ?string $version = null;

    protected array $comments = [];

    protected ?Entries $entries = null;

    /**
     * @param array|stdClass $data json_decoded data
     */
    public function __construct(array|stdClass $data)
    {
        $this->mock((array) $data);
    }

    /**
     * @param string $file
     * @return static
     */
    public static function loadFromFile(string $file): static
    {
        if (!is_file($file)) {
            throw new FileNotFoundException(
                $file
            );
        }
        if (filesize($file) > self::MAX_FILE_SIZE) {
            throw new OutOfRangeException(
                sprintf('File size is too big. Maximum allowed size is : %d bytes.', self::MAX_FILE_SIZE)
            );
        }
        $data = '';
        $fp = fopen($file, 'r');
        while (!feof($fp)) {
            $data .= fread($fp, 4096);
        }
        fclose($fp);
        $data = json_decode($data, true);
        if (!is_array($data)) {
            throw new InvalidDataException(
                'File is not valid json array'
            );
        }
        return new static($data);
    }

    private function mock(array $data): void
    {
        if (isset($data[self::LOCALE_DATA_KEY])
            && is_array($data[self::LOCALE_DATA_KEY])
        ) {
            $translations = $data[self::LOCALE_DATA_KEY][self::MESSAGES_KEY]??[];
        } else {
            $translations = $data['translations']??[];
        }

        unset($data[self::LOCALE_DATA_KEY], $data['translations']);
        $translations = !is_array($translations) ? [] : $translations;
        // message metadata is empty string
        $messages = $translations['']??[];
        $messages = !is_array($messages) ? [] : $messages;
        unset($translations['']);
        foreach ($messages as $key => $item) {
            $this->metadata[$key] = $item;
        }

        $messages = array_change_key_case($messages);
        $this->version = $messages[self::VERSION_KEY]??$data[self::VERSION_KEY]??null;
        $this->generator = $messages[self::GENERATOR_KEY]
            ??$data[self::GENERATOR_KEY]
            ??null;
        $language =  $messages[self::LANGUAGE_KEY]??$messages['language']??$messages['locale']??null;
        $language ??=  $data[self::LANGUAGE_KEY]??$data['language']??$data['locale']??null;

        $language = is_string($language) ? $language : $this->language;
        $this->language = $language ? Locale::normalizeLocale($language) : $this->language;

        $domain   =  $messages[self::DOMAIN_KEY]??$messages['text-domain']??$messages['text_domain']??null;
        $domain ??= $data[self::DOMAIN_KEY]??$data['text-domain']??$data['text_domain']??null;
        $this->domain = $domain??$this->domain;

        $revisionDate = $data[self::REVISION_KEY]??$data['revision-date']??null;
        $creationDate = $messages[self::CREATION_DATE_KEY]??$data[self::CREATION_DATE_KEY]??null;

        if (is_string($revisionDate)) {
            try {
                $this->revisionDate = new DateTimeImmutable($revisionDate);
            } catch (Throwable) {
            }
        }
        if (is_string($creationDate)) {
            try {
                $this->creationDate = new DateTimeImmutable($creationDate);
            } catch (Throwable) {
            }
        }
        $comments = $data[self::COMMENT_KEY]??$data['comments']??[];
        if (is_array($comments)) {
            foreach ($comments as $key => $item) {
                if (!is_string($key) && !is_string($item)) {
                    continue;
                }
                $this->comments[$key] = $item;
            }
        }
        $pluralForm = $messages[self::PLURAL_FORMS_KEY]??$data[self::PLURAL_FORMS_KEY]??null;
        if (is_string($pluralForm)) {
            try {
                if (($output = PluralParser::parseFunction($pluralForm))
                    && !empty($output)
                ) {
                    $this->pluralForms = PluralParser::createPluralFormFromPluralString($pluralForm);
                }
            } catch (RuntimeException) {
            }
        }
        $this->entries = new Entries();
        foreach ($translations as $original => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entry = array_values($entry);
            foreach ($entry as $item) {
                if (!is_string($item)) {
                    continue 2;
                }
            }
            $this->entries->add(
                Entry::create(
                    (string) $original,
                    null,
                    $entry,
                    null,
                    $this->pluralForms
                )
            );
        }
    }

    public function getEntries(): Entries
    {
        return $this->entries;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getGenerator(): ?string
    {
        return $this->generator;
    }

    public function getPluralForms(): ?PluralForm
    {
        return $this->pluralForms;
    }

    public function getCreationDate(): ?DateTimeInterface
    {
        return $this->creationDate;
    }

    public function getRevisionDate(): ?DateTimeInterface
    {
        return $this->revisionDate;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getComments(): array
    {
        return $this->comments;
    }
}
