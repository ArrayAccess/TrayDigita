<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Abstracts;

use ArrayAccess\TrayDigita\Database\TypeList;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;

#[Index(
    columns: ['name'],
    name: 'index_name'
)]
#[HasLifecycleCallbacks]
/**
 * @property-read string $name
 * @property-read mixed $value
 * @property-read DateTimeInterface $created_at
 * @property-read DateTimeInterface $updated_at
 */
abstract class AbstractBasedMeta extends AbstractEntity
{
    #[Id]
    #[Column(
        name: 'name',
        type: Types::STRING,
        length: 255,
        nullable: false,
        options: [
            'comment' => 'Metadata name as primary key composite id'
        ]
    )]
    protected string $name;

    #[Column(
        name: 'value',
        type: TypeList::DATA,
        length: 4294967295,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Metadata values'
        ]
    )]
    protected mixed $value = null;

    #[Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        unique: false,
        nullable: false,
        updatable: false,
        options: [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'Metadata created time'
        ]
    )]
    protected DateTimeInterface $created_at;

    #[Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        unique: false,
        updatable: false,
        options: [
            'attribute' => 'ON UPDATE CURRENT_TIMESTAMP',
            'default' => '0000-00-00 00:00:00',
            'comment' => 'Metadata updated time'
        ],
        // columnDefinition: "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP"
    )]
    protected DateTimeInterface $updated_at;

    public function __construct()
    {
        $this->created_at = new DateTimeImmutable();
        $this->updated_at = new DateTimeImmutable('0000-00-00 00:00:00');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updated_at;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
