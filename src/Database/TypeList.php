<?php
/** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database;

use ArrayAccess\TrayDigita\Database\Types\Data;
use ArrayAccess\TrayDigita\Database\Types\DataBlob;
use ArrayAccess\TrayDigita\Database\Types\DateTypeWrapper;
use ArrayAccess\TrayDigita\Database\Types\Year;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\PhpDateTimeMappingType;
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
        self::YEAR => Year::class
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

        $typeRegistry = Type::getTypeRegistry();
        foreach (Type::getTypesMap() as $name => $className) {
            /** @noinspection PhpInternalEntityUsedInspection */
            if (isset(self::TYPES[$name])
                || is_subclass_of($className, DateTypeWrapper::class)
                || (
                    !is_subclass_of($className, DateType::class)
                    && ! is_subclass_of($className, PhpDateTimeMappingType::class)
                )
            ) {
                continue;
            }
            try {
                $type = $typeRegistry->get($name);
                $typeRegistry->override($name, DateTypeWrapper::wrapType($type));
            } catch (Exception) {
                continue;
            }
        }
    }
}
