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
    const DATA = Data::NAME;
    const YEAR = Year::NAME;
    const DATA_BLOB = DataBlob::NAME;

    const TYPES = [
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
