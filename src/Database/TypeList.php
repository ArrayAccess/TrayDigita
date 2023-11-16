<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database;

use ArrayAccess\TrayDigita\Database\Types\Data;
use ArrayAccess\TrayDigita\Database\Types\DataBlob;
use ArrayAccess\TrayDigita\Database\Types\Year;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Type;

final class TypeList
{
    private static bool $registered = false;

    public const DATA = Data::NAME;

    public const YEAR = Year::NAME;

    public const DATA_BLOB = DataBlob::NAME;

    public const TYPES = [
        self::DATA => Data::class,
        self::DATA_BLOB => DataBlob::class,
        self::YEAR => Year::class,
    ];

    public static function registerDefaultTypes(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        foreach (self::TYPES as $type => $className) {
            if (!Type::hasType($type)) {
                try {
                    Type::addType($type, $className);
                } catch (Exception) {
                }
            }
        }
    }
}
