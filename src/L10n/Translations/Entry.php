<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations;

use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\PluralForm;
use ArrayAccess\TrayDigita\L10n\PoMo\Translation;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use function array_values;
use function is_string;

class Entry implements EntryInterface
{
    /**
     * @var string
     */
    private string $id;

    /**
     * @var array|string[]
     */
    private array $translations;

    /**
     * @var ?PluralForm
     */
    protected ?PluralForm $pluralForm;

    /**
     * @var string
     */
    private string $original;
    /**
     * @var ?string
     */
    private ?string $plural;
    /**
     * @var ?string
     */
    private ?string $context;

    /**
     * @param string $original
     * @param string|null $plural
     * @param array<int,string> $translations
     * @param string|null $context
     * @param PluralForm|null $pluralForm
     */
    public function __construct(
        string $original = '',
        ?string $plural = null,
        array|string $translations = [],
        ?string $context = null,
        ?PluralForm $pluralForm = null
    ) {
        $this->original = $original;
        $this->plural = $plural;
        $this->context = $context;
        $this->id = self::generateId($context, $this->original);
        $translations = is_string($translations) ? [$translations] : $translations;
        $this->translations = array_values($translations);
        $this->pluralForm = $pluralForm;
    }

    /**
     * @param string $original
     * @param string|null $plural
     * @param array $translations
     * @param string|null $context
     * @param PluralForm|null $pluralForm
     *
     * @return static
     */
    public static function create(
        string $original = '',
        ?string $plural = null,
        array $translations = [],
        ?string $context = null,
        ?PluralForm $pluralForm = null
    ) : static {
        return new static($original, $plural, $translations, $context, $pluralForm);
    }

    /**
     * @return ?string
     */
    public function getContext() : ?string
    {
        return $this->context;
    }

    public static function generateId(?string $context, string $original) : string
    {
        return Translation::generateId($context, $original);
    }

    public function getOriginal() : string
    {
        return $this->original;
    }

    /**
     * @return ?string
     */
    public function getPlural() : ?string
    {
        return $this->plural;
    }

    public function getTranslations() : array
    {
        return $this->translations;
    }

    public function getTranslationIndex(int $n = 0) : ?string
    {
        return $this->translations[$n]??null;
    }

    /**
     * @return ?PluralForm
     */
    public function getPluralForm() : ?PluralForm
    {
        return $this->pluralForm;
    }

    /**
     * @param ?PluralForm $pluralForm
     */
    public function setPluralForm(?PluralForm $pluralForm) : void
    {
        $this->pluralForm = $pluralForm;
    }

    public function getId() : string
    {
        return $this->id;
    }
}
