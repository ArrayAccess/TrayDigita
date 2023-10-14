<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Abstracts;

use ArrayAccess\TrayDigita\Database\TypeList;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;

#[Index(
    columns: ['user_id', 'name', 'created_at', 'updated_at'],
    name: 'index_user_id_name_created_at_updated_at'
)]
/**
 * @property-read int $user_id
 * @property-read ?string $name
 * @property-read DateTimeInterface $created_at
 * @property-read DateTimeInterface $updated_at
 */
abstract class AbstractBasedOnlineActivity extends AbstractEntity
{
    #[Id]
    #[Column(
        name: 'user_id',
        type: Types::BIGINT,
        length: 20,
        updatable: false,
        options: [
            'unsigned' => true,
            'comment' => 'User based identifier'
        ]
    )]
    protected int $user_id;

    #[Column(
        name: 'name',
        type: Types::STRING,
        length: 255,
        nullable: true,
        options: [
            'comment' => 'Optional identity'
        ]
    )]
    protected ?string $name = null;

    #[Column(
        name: 'content',
        type: TypeList::DATA,
        length: 4294967295,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Activity data'
        ]
    )]
    protected mixed $content = null;

    #[Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        unique: false,
        nullable: false,
        updatable: false,
        options: [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'Activity first created'
        ]
    )]
    protected DateTimeInterface $created_at;

    #[Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        unique: false,
        nullable: false,
        updatable: false,
        options: [
            'attribute' => 'ON UPDATE CURRENT_TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'Activity updated time'
        ],
        // columnDefinition: "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    )]
    protected DateTimeInterface $updated_at;

    public function __construct()
    {
        $this->name = null;
        $this->content = null;
        $this->created_at = new DateTimeImmutable();
        $this->updated_at = new DateTimeImmutable();
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    public function setContent(mixed $content): void
    {
        $this->content = $content;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updated_at;
    }
}
