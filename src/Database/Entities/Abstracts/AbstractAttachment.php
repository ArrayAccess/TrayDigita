<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Abstracts;

use ArrayAccess\TrayDigita\Database\Entities\Interfaces\AvailabilityStatusEntityInterface;
use ArrayAccess\TrayDigita\Database\Entities\Interfaces\IdentityBasedEntityInterface;
use ArrayAccess\TrayDigita\Database\Entities\Traits\AvailabilityStatusTrait;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Doctrine\ORM\Mapping\UniqueConstraint;
use function ltrim;
use function str_starts_with;
use function strtolower;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $file_name
 * @property-read ?string $description
 * @property-read ?int $user_id
 * @property-read string $path
 * @property-read string $mime_type
 * @property-read int $size
 * @property-read string $status
 * @property-read DateTimeInterface $created_at
 * @property-read DateTimeInterface $updated_at
 * @property-read ?DateTimeInterface $deleted_at
 */
#[UniqueConstraint(
    name: 'unique_path_storage_type',
    columns: ['path', 'storage_type']
)]
#[Index(
    columns: ['storage_type', 'mime_type'],
    name: 'index_storage_type_mime_type'
)]
#[Index(
    columns: ['user_id'],
    name: 'relation_attachments_user_id_admins_id'
)]
#[Index(
    columns: ['name', 'file_name', 'status', 'mime_type', 'storage_type'],
    name: 'index_name_file_name_status_mime_type_storage_type'
)]
#[HasLifecycleCallbacks]
abstract class AbstractAttachment extends AbstractEntity implements
    IdentityBasedEntityInterface,
    AvailabilityStatusEntityInterface
{
    use AvailabilityStatusTrait;

    public const TYPE_DATA = 'data';
    public const TYPE_UPLOAD = 'upload';
    public const TYPE_AVATAR = 'avatar';

    #[Id]
    #[GeneratedValue('AUTO')]
    #[Column(
        name: 'id',
        type: Types::BIGINT,
        length: 20,
        updatable: false,
        options: [
            'unsigned' => true,
            'comment' => 'Attachment Id'
        ]
    )]
    protected int $id;

    #[Column(
        name: 'name',
        type: Types::STRING,
        length: 255,
        nullable: false,
        options: [
            'comment' => 'Attachment name'
        ]
    )]
    protected string $name;

    #[Column(
        name: 'file_name',
        type: Types::STRING,
        length: 255,
        nullable: false,
        options: [
            'comment' => 'Attachment file name'
        ]
    )]
    protected string $file_name;

    #[Column(
        name: 'description',
        type: Types::TEXT,
        length: AbstractMySQLPlatform::LENGTH_LIMIT_TEXT,
        nullable: true,
        options:  [
            'default' => null,
            'comment' => 'Attachment description'
        ]
    )]
    protected ?string $description = null;

    #[Column(
        name: 'user_id',
        type: Types::BIGINT,
        length: 20,
        nullable: true,
        updatable: true,
        options: [
            'unsigned' => true,
            'default' => null,
            'comment' => 'User id uploader'
        ]
    )]
    protected ?int $user_id = null;

    /**
     * @var string
     * @link https://www.ibm.com/docs/en/spectrum-protect/8.1.9?topic=parameters-file-specification-syntax
     * commonly 1024 char length (see #2), but we make safe on 2048
     *
     * 1. Linux: The maximum length for a file name is 255 bytes.
     * The maximum combined length of both the file name and path name is 4096 bytes.
     * This length matches the PATH_MAX that is supported by the operating system.
     * The Unicode representation of a character can occupy several bytes,
     * so the maximum number of characters that comprises a path and file name can vary.
     * The actual limitation is the number of bytes in the path and file components,
     * which might correspond to an equal number of characters.
     * 2. For archive or retrieve operations,
     * the maximum length that you can specify for a path and file name (combined) remains at 1024 bytes
     */
    #[Column(
        name: 'path',
        type: Types::STRING,
        length: 2048,
        nullable: false,
        options: [
            'comment' => 'Path to file'
        ]
    )]
    protected string $path;

    #[Column(
        name: 'storage_type',
        type: Types::STRING,
        length: 64,
        nullable: false,
        options: [
            'comment' => 'Attachment storage type (upload/data)'
        ]
    )]
    protected string $storage_type;

    /**
     * Commonly mime type use 64 or less character length.
     * but in :
     * application/vnd.openxmlformats-officedocument.spreadsheetml.pivotCacheDefinition+xml
     * contain 84 characters, use standard is 128 for safe result
     *
     * @var string $mime_type
     */
    #[Column(
        name: 'mime_type',
        type: Types::STRING,
        length: 128,
        nullable: false,
        options: [
            'comment' => 'Attachment mimetype'
        ]
    )]
    protected string $mime_type;

    #[Column(
        name: 'size',
        type: Types::STRING,
        length: 20,
        nullable: false,
        options: [
            'unsigned' => true,
            'comment' => 'Attachment size'
        ]
    )]
    protected int $size;

    #[Column(
        name: 'status',
        type: Types::STRING,
        length: 64,
        nullable: false,
        options: [
            'default' => self::PUBLISHED,
            'comment' => 'Attachment status'
        ]
    )]
    protected string $status;

    #[Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        updatable: false,
        options: [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'Attachment created time'
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
            'comment' => 'Attachment update time'
        ],
        // columnDefinition: "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP"
    )]
    protected DateTimeInterface $updated_at;

    #[Column(
        name: 'deleted_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Attachment delete time'
        ]
    )]
    protected ?DateTimeInterface $deleted_at = null;

    /**
     * Allow associations mapping
     * @see jsonSerialize()
     *
     * @var bool
     */
    protected bool $entityAllowAssociations = true;

    public function __construct()
    {
        $this->created_at = new DateTimeImmutable();
        $this->updated_at = new DateTimeImmutable('0000-00-00 00:00:00');
        $this->deleted_at = null;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getFileName(): string
    {
        return $this->file_name;
    }

    public function setFileName(string $file_name): void
    {
        $this->file_name = $file_name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(?int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getMimeType(): string
    {
        return $this->mime_type;
    }

    public function setMimeType(string $mime_type): void
    {
        $this->mime_type = strtolower(trim($mime_type));
    }

    public function getStorageType(): string
    {
        return $this->storage_type;
    }

    public function setStorageType(string $storage_type): void
    {
        $this->storage_type = strtolower(trim($storage_type));
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updated_at;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?DateTimeInterface $deleted_at): void
    {
        $this->deleted_at = $deleted_at;
    }

    #[
        PrePersist,
        PreUpdate
    ]
    public function beforeSave(PrePersistEventArgs|PreUpdateEventArgs $event): void
    {
        if ($event instanceof PreUpdateEventArgs) {
            if (!$event->hasChangedField('path')) {
                return;
            }
            $oldPath = $event->getNewValue('path');
            if (str_starts_with($oldPath, '/')) {
                $path = ltrim($oldPath, '/');
                $this->setPath($path);
                $event->setNewValue('path', $path);
            }
            return;
        }

        if (str_starts_with($this->getPath(), '/')) {
            $path = ltrim($this->getPath(), '/');
            $this->setPath($path);
        }
    }
}
