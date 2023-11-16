<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Logger\Entities;

use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;

/**
 * @see \ArrayAccess\TrayDigita\Logger\Handler\DatabaseHandler
 * @property-read int $id
 * @property-read string $channel
 * @property-read string $level
 * @property-read string $message
 * @property-read int $created_time
 */
#[Entity]
#[Table(
    name: self::TABLE_NAME,
    options: [
        'charset' => 'utf8mb4', // remove this or change to utf8 if not use mysql
        'collation' => 'utf8mb4_unicode_ci',  // remove this if not use mysql
        'comment' => 'Record for logs'
    ]
)]
#[Index(
    columns: ['channel', 'level'],
    name: 'index_channel_level'
)]
#[HasLifecycleCallbacks]
class LogItem extends AbstractEntity
{
    public const TABLE_NAME = 'log_items';
    
    #[Id]
    #[GeneratedValue('AUTO')]
    #[Column(
        name: 'id',
        type: Types::BIGINT,
        length: 20,
        updatable: false,
        options: [
            'comment' => 'Primary key id'
        ]
    )]
    protected int $id;

    #[Column(
        name: 'channel',
        type: Types::STRING,
        length: 255,
        options: [
            'default' => 'default',
            'comment' => 'Log channel'
        ]
    )]
    protected string $channel = 'default';

    #[Column(
        name: 'level',
        type: Types::STRING,
        length: 10,
        options: [
            'comment' => 'Log level'
        ]
    )]
    protected string $level;

    #[Column(
        name: 'message',
        type: Types::BLOB,
        length: AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB,
        options: [
            'comment' => 'Log message records'
        ]
    )]
    protected string $message;

    #[Column(
        name: 'created_time',
        type: Types::INTEGER,
        length: 10,
        options: [
            'unsigned' => true,
            'comment' => 'Log time'
        ]
    )]
    protected int $created_time;

    public function __construct()
    {
        $this->created_time = time();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): void
    {
        $this->level = $level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getCreatedTime(): int
    {
        return $this->created_time;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return DateTimeImmutable::createFromFormat(
            DateTimeInterface::RFC3339,
            date(DateTimeInterface::RFC3339, $this->created_time)
        );
    }

    public function setCreatedTime(int|DateTimeInterface $created_time): void
    {
        if ($created_time instanceof DateTimeInterface) {
            $created_time = $created_time->getTimestamp();
        }
        $this->created_time = $created_time;
    }
}
