<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Abstracts;

use ArrayAccess\TrayDigita\Database\Entities\Interfaces\IdentityBasedEntityInterface;
use ArrayAccess\TrayDigita\Database\TypeList;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;

#[Index(
    name: 'relation_admin_logs_user_id_admins_id',
    columns: ['user_id']
)]
#[Index(
    name: 'index_user_id_name_type',
    columns: ['user_id', 'name', 'type']
)]
#[HasLifecycleCallbacks]
/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $name
 * @property-read ?string $type
 * @property-read mixed $value
 * @property-read DateTimeInterface $created_at
 * @property-read DateTimeInterface $updated_at
 */
abstract class AbstractUserBasedLog extends AbstractEntity implements IdentityBasedEntityInterface
{
    #[Id]
    #[GeneratedValue('AUTO')]
    #[Column(
        name: 'id',
        type: Types::BIGINT,
        length: 20,
        options: [
            'unsigned' => true,
            'comment' => 'Primary key od'
        ]
    )]
    protected int $id;

    #[Column(
        name: 'user_id',
        type: Types::BIGINT,
        length: 20,
        nullable: false,
        options: [
            'unsigned' => true,
            'comment' => 'User id relation'
        ]
    )]
    protected int $user_id;

    #[Column(
        name: 'name',
        type: Types::STRING,
        length: 255,
        nullable: false,
        options: [
            'comment' => 'Log name'
        ]
    )]
    protected string $name;

    #[Column(
        name: 'type',
        type: Types::STRING,
        length: 20,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Log type'
        ]
    )]
    protected ?string $type = null;

    #[Column(
        name: 'value',
        type: TypeList::DATA,
        length: 4294967295,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Log data'
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
            'comment' => 'Log created time'
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
            'default' => '0000-00-00 00:00:00',
            'comment' => 'Log updated time'
        ],
        // columnDefinition: "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP"
    )]
    protected DateTimeInterface $updated_at;

    public function __construct()
    {
        $this->created_at = new DateTimeImmutable();
        $this->updated_at = new DateTimeImmutable('0000-00-00 00:00:00');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
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
