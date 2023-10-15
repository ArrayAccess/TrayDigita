<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo;

use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\Comments;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\Flags;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\PluralForm;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\References;
use function array_slice;
use function array_values;

class Translation
{
    /**
     * @var bool
     */
    private bool $enable = true;

    /**
     * @var ?string
     */
    private ?string $context;

    /**
     * @var ?string
     */
    private ?string $original;

    /**
     * @var ?string
     */
    protected ?string $translation;

    /**
     * @var ?string
     */
    protected ?string $plural;

    /**
     * @var array|string[]
     */
    protected array $pluralTranslations;

    /**
     * @var Flags
     */
    protected Flags $flags;

    /**
     * @var References
     */
    protected References $references;

    /**
     * @var Comments
     */
    protected Comments $comments;

    /**
     * @var Comments
     */
    protected Comments $extractedComments;

    /**
     * @var ?PluralForm
     */
    protected ?PluralForm $pluralForm = null;

    /**
     * @param string $id
     */
    protected function __construct(private string $id)
    {
        $this->flags = new Flags();
        $this->comments = new Comments();
        $this->extractedComments = new Comments();
        $this->references = new References();
    }

    public function __clone()
    {
        $this->references = clone $this->references;
        $this->flags = clone $this->flags;
        $this->comments = clone $this->comments;
        $this->extractedComments = clone $this->extractedComments;
    }

    /**
     * @param ?string $context
     * @param string $original
     * @param string|null $plural
     * @param string|null $translation
     * @param string ...$pluralTranslations
     *
     * @return static
     */
    public static function create(
        ?string $context,
        string $original,
        ?string $plural = null,
        ?string $translation = null,
        string ...$pluralTranslations
    ) : static {
        $id = self::generateId($context, $original);
        $object = new static($id);
        $object->context = $context;
        $object->original = $original;
        $object->setPlural($plural);
        $object->setTranslation($translation);
        $object->setPluralTranslations(...$pluralTranslations);
        return $object;
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
    public function setPluralForm(?PluralForm $pluralForm): void
    {
        $this->pluralForm = $pluralForm;
    }

    /**
     * @param ?string $context
     *
     * @param string $original
     *
     * @return ?string
     */
    public static function generateId(?string $context, string $original) : ?string
    {
        return "$context\4$original";
    }

    /**
     * @param string $original
     *
     * @return static
     */
    public function withOriginal(string $original) : static
    {
        $clone = clone $this;
        $clone->original = $original;
        $clone->id = static::generateId($clone->getContext(), $clone->getOriginal());
        return $clone;
    }

    /**
     * @param string $context
     *
     * @return static
     */
    public function withContext(string $context) : static
    {
        $clone = clone $this;
        $clone->context = $context;
        $clone->id = static::generateId($context, $clone->getOriginal());
        return $clone;
    }

    /**
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isEnable() : bool
    {
        return $this->enable;
    }

    /**
     * @param bool $enable
     *
     * @return Translation
     */
    public function setEnable(bool $enable) : static
    {
        $this->enable = $enable;
        return $this;
    }

    public function enable() : static
    {
        return $this->setEnable(true);
    }

    public function disable() : static
    {
        return $this->setEnable(false);
    }

    /**
     * @return array<int, string>
     */
    public function getPluralTranslations(int $pluralSize = null) : array
    {
        if ($pluralSize === null || $pluralSize < 0) {
            return $this->pluralTranslations;
        }
        return array_values(array_slice($this->pluralTranslations, 0, $pluralSize));
    }

    /**
     * @param array|string[] $pluralTranslations
     *
     * @return static
     */
    public function setPluralTranslations(string ...$pluralTranslations) : static
    {
        $this->pluralTranslations = array_values($pluralTranslations);
        return $this;
    }

    /**
     * @return References
     */
    public function getReferences() : References
    {
        return $this->references;
    }

    /**
     * @return Comments
     */
    public function getComments() : Comments
    {
        return $this->comments;
    }

    /**
     * @return Flags
     */
    public function getFlags() : Flags
    {
        return $this->flags;
    }

    /**
     * @return Comments
     */
    public function getExtractedComments() : Comments
    {
        return $this->extractedComments;
    }

    /**
     * @return string
     */
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

    /**
     * @param string|null $plural
     *
     * @return static
     */
    public function setPlural(?string $plural) : static
    {
        $this->plural = $plural;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTranslation() : ?string
    {
        return $this->translation;
    }

    /**
     * @param ?string $translation
     *
     * @return Translation
     */
    public function setTranslation(?string $translation) : static
    {
        $this->translation = $translation;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getContext() : ?string
    {
        return $this->context;
    }
}
