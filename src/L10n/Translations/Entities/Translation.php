<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Entities;

use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @property-read int $id
 * @property-read string $language
 * @property-read string $domain
 * @property-read string $original
 * @property-read ?string $translation
 * @property-read ?string $plural_translation
 * @property-read ?string $context
 */
#[Entity]
#[Table(
    name: self::TABLE_NAME,
    options: [
        'charset' => 'utf8mb4', // remove this or change to utf8 if not use mysql
        'collation' => 'utf8mb4_unicode_ci',  // remove this if not use mysql
        'comment' => 'Table translations',
    ]
)]
#[UniqueConstraint(
    name: 'unique_language_domain_original',
    columns: [
        'language',
        'domain',
        'original'
    ]
)]
#[Index(
    columns: ['language'],
    name: 'index_language'
)]
#[Index(
    columns: ['domain'],
    name: 'index_domain'
)]
#[HasLifecycleCallbacks]
class Translation extends AbstractEntity
{
    const TABLE_NAME = 'translations';
    
    #[Id]
    #[GeneratedValue('AUTO')]
    #[Column(
        name: 'id',
        type: Types::BIGINT,
        length: 20,
        updatable: false,
        options: [
            'unsigned' => true,
            'comment' => 'Primary key id'
        ]
    )]
    protected int $id;

    #[Column(
        name: 'language',
        type: Types::STRING,
        length: 10,
        nullable: false,
        options: [
            'comment' => 'Language'
        ]
    )]
    protected string $language;

    #[Column(
        name: 'domain',
        type: Types::STRING,
        length: 128,
        nullable: false,
        options: [
            'default' => TranslatorInterface::DEFAULT_DOMAIN,
            'comment' => 'Text domain'
        ]
    )]
    protected string $domain = TranslatorInterface::DEFAULT_DOMAIN;

    #[Column(
        name: 'original',
        type: Types::STRING,
        length: 1024,
        nullable: false,
        options: [
            'comment' => 'Original text'
        ]
    )]
    protected string $original;

    #[Column(
        name: 'translation',
        type: Types::STRING,
        length: 1024,
        nullable: true,
        options: [
            'comment' => 'Translated text'
        ]
    )]
    protected string $translation;

    #[Column(
        name: 'plural_translation',
        type: Types::STRING,
        length: 1024,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Plural Translated text'
        ]
    )]
    protected ?string $plural_translation;

    #[Column(
        name: 'context',
        type: Types::STRING,
        length: 2048,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Context for the translator'
        ]
    )]
    protected ?string $context;

    public function __construct()
    {
        $this->domain = TranslatorInterface::DEFAULT_DOMAIN;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getOriginal(): string
    {
        return $this->original;
    }

    public function setOriginal(string $original): void
    {
        $this->original = $original;
    }

    public function getTranslation(): string
    {
        return $this->translation;
    }

    public function setTranslation(string $translation): void
    {
        $this->translation = $translation;
    }

    public function getPluralTranslation(): ?string
    {
        return $this->plural_translation;
    }

    public function setPluralTranslation(?string $plural_translation): void
    {
        $this->plural_translation = $plural_translation;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): void
    {
        $this->context = $context;
    }
}
