<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Throwable;
use function is_numeric;
use function is_resource;
use function preg_match;
use function stream_get_contents;
use function strlen;

class Year extends DateTimeType
{
    public const NAME = 'year';

    /**
     * {@inheritDoc}
     *
     * @param T $value
     *
     * @return (T is null ? null : DateTimeInterface)
     *
     * @template T
     * @throws InvalidFormat
     */
    public function convertToPHPValue($value, AbstractPlatform $platform) : ?DateTime
    {
        if ($value === null || $value instanceof DateTimeInterface) {
            return $value;
        }
        if (strlen($value) === 4) {
            $value = "$value-01-01";
        }
        $val = DateTime::createFromFormat('!Y-m-d', $value);
        if ($val === false) {
            throw InvalidFormat::new(
                $value,
                static::class,
                $platform->getDateTimeFormatString()
            );
        }
        return $val;
    }

    /**
     * @param $value
     * @param AbstractPlatform $platform
     * @return string|null
     * @throws InvalidType
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = is_resource($value) ? stream_get_contents($value) : $value;
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y');
        }
        if (is_numeric($value)) {
            $value = (string) $value;
            if (preg_match('~[^0-9]~', $value)) {
                throw InvalidType::new(
                    $value,
                    static::class,
                    ['null', 'DateTime', 'int', 'string']
                );
            }
        }
        if (is_string($value)) {
            if ($value[0] === '-') {
                $value = intval(substr($value, 0, 4));
                $value += 1;
            }
            return str_pad((string) $value, 4, '0', STR_PAD_LEFT);
        }
        throw InvalidType::new(
            $value,
            static::class,
            ['null', 'DateTime', 'int', 'string']
        );
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($platform->hasDoctrineTypeMappingFor('year')) {
            return 'YEAR';
        }
        try {
            return $platform->getDateTypeDeclarationSQL($column);
        } catch (Throwable) {
            return 'YEAR';
        }
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['year'];
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
