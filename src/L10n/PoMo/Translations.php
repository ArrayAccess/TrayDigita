<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo;

use ArrayIterator;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\Flags;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\Headers;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\PluralForm;
use Countable;
use IteratorAggregate;
use Traversable;
use function sprintf;

class Translations implements Countable, IteratorAggregate
{
    /**
     * @var ?string
     */
    protected ?string $description = null;

    /**
     * @var Headers
     */
    private Headers $headers;

    /**
     * @var Flags
     */
    private Flags $flags;

    /**
     * @var array<Translation>
     */
    private array $translations;

    /**
     * @param ?int $revision
     */
    public function __construct(
        private ?int $revision = null
    ) {
        $this->translations = [];
        $this->headers = new Headers();
        $this->flags   = new Flags();
    }

    /**
     * @return Flags
     */
    public function getFlags() : Flags
    {
        return $this->flags;
    }

    /**
     * @param string $language
     *
     * @return $this
     */
    public function setLanguage(string $language) : static
    {
        $info = Locale::getInfo($language);
        if (!$info) {
            throw new InvalidArgumentException(
                sprintf('Language "%s" is not valid', $language)
            );
        }
        $this->getHeaders()
            ->setLanguage($info['id'])
            ->setPluralForms($info['count'], $info['expression']);
        return $this;
    }

    public function setTranslationsPluralForm(PluralForm $pluralForm) : static
    {
        foreach ($this->translations as $translation) {
            $translation->setPluralForm($pluralForm);
        }
        return $this;
    }

    /**
     * @param string $domain
     *
     * @return $this
     */
    public function setDomain(string $domain) : static
    {
        $this->getHeaders()->setDomain($domain);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription() : ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     */
    public function setDescription(?string $description) : void
    {
        $this->description = $description;
    }

    /**
     * @return ?int
     */
    public function getRevision() : ?int
    {
        return $this->revision;
    }

    /**
     * @param int|null $revision
     */
    public function setRevision(?int $revision) : void
    {
        $this->revision = $revision;
    }

    /**
     * @param Translation $translation
     */
    public function add(Translation $translation): void
    {
        $this->translations[$translation->getId()] = $translation;
    }

    /**
     * @param Translation ...$translations
     */
    public function merge(Translation ...$translations): void
    {
        foreach ($translations as $translation) {
            $id = $translation->getId();
            $exists = isset($this->translations[$id]);
            if ($exists && (!$translation->isEnable() || $translation->getTranslation() === '')) {
                continue;
            }
            $this->translations[$id] = $translation;
        }
    }

    /**
     * @param ?string $context
     * @param string|Translation $original
     *
     * @return Translation|null
     */
    public function find(string|Translation $original, ?string $context = null) : ?Translation
    {
        $id = $original instanceof Translation
            ? $original->getId()
            : Translation::generateId($context, $original);
        return $this->translations[$id]??null;
    }

    public function has(string|Translation $original, ?string $context = null) : bool
    {
        $id = $original instanceof Translation
            ? $original->getId()
            : Translation::generateId($context, $original);
        return isset($this->translations[$id]);
    }

    public function remove(string|Translation $original, ?string $context = null): void
    {
        $id = $original instanceof Translation
            ? $original->getId()
            : Translation::generateId($context, $original);
        unset($this->translations[$id]);
    }

    /**
     * @return Headers
     */
    public function getHeaders() : Headers
    {
        return $this->headers;
    }

    public function getTranslations() : array
    {
        return $this->translations;
    }

    public function count() : int
    {
        return count($this->translations);
    }

    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->getTranslations());
    }
}
