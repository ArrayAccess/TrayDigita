<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Cache\Entities;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * @see \Symfony\Component\Cache\Adapter\DoctrineDbalAdapter
 * @property-read string $item_id
 * @property-read mixed $item_data
 * @property-read int $item_time
 * @property-read int $item_lifetime
 */
#[Entity]
#[Table(
    name: self::TABLE_NAME,
    options: [
        'charset' => 'utf8mb4', // remove this or change to utf8 if not use mysql
        'collation' => 'utf8mb4_unicode_ci',  // remove this if not use mysql
        'comment' => 'Cache items'
    ]
)]
#[HasLifecycleCallbacks]
class CacheItem extends AbstractEntity
{
    public const TABLE_NAME = 'cache_items';

    #[Id]
    #[Column(
        name: 'item_id',
        type: Types::BINARY,
        length: 255,
        options: [
            'comment' => 'Item id'
        ]
    )]
    protected string $item_id;

    #[Column(
        name: 'item_data',
        type: Types::BLOB,
        length: AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB,
        options: [
            'comment' => 'Cache item data'
        ]
    )]
    protected mixed $item_data;

    #[Column(
        name: 'item_lifetime',
        type: Types::INTEGER,
        length: 10,
        nullable: true,
        options: [
            'unsigned' => true,
            'comment' => 'Cache item lifetime'
        ]
    )]
    protected ?int $item_lifetime;

    #[Column(
        name: 'item_time',
        type: Types::INTEGER,
        length: 10,
        options: [
            'unsigned' => true,
            'comment' => 'Cache item timestamp'
        ]
    )]
    protected int $item_time;

    public function getItemId(): string
    {
        return $this->item_id;
    }

    public function setItemId(string $item_id): void
    {
        $this->item_id = $item_id;
    }

    public function getItemData(): mixed
    {
        return $this->item_data;
    }

    public function setItemData(mixed $item_data): void
    {
        $this->item_data = $item_data;
    }

    public function getItemLifetime(): ?int
    {
        return $this->item_lifetime;
    }

    public function setItemLifetime(?int $item_lifetime): void
    {
        $this->item_lifetime = $item_lifetime;
    }

    public function getItemTime(): int
    {
        return $this->item_time;
    }

    public function setItemTime(int $item_time): void
    {
        $this->item_time = $item_time;
    }

    public static function addEntityToMetadata(
        Connection|EntityManagerInterface $connection
    ): void {
        if ($connection instanceof Connection) {
            $connection = $connection->getEntityManager();
        }
        $metadataFactory = $connection->getMetadataFactory();
        if ($metadataFactory->hasMetadataFor(__CLASS__)) {
            return;
        }
        $configuration = $connection->getConfiguration();
        $metadataFactory->setMetadataFor(
            __CLASS__,
            new ClassMetadata(
                __CLASS__,
                $configuration->getNamingStrategy(),
                $configuration->getTypedFieldMapper()
            )
        );
    }
}
