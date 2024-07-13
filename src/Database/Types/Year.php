<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;
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
     */
    public function convertToPHPValue($value, AbstractPlatform $platform) : ?DateTimeInterface
    {
        if ($value === null || $value instanceof DateTimeInterface) {
            return $value;
        }
        if (strlen($value) === 4) {
            $value = "$value-01-01";
        }
        $val = DateTime::createFromFormat('!Y-m-d', $value);
        if ($val === false) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                'Y-m-d',
            );
        }
        return $val;
    }

    /**
     * @param $value
     * @param AbstractPlatform $platform
     * @return string|null
     * @throws ConversionException
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
                throw ConversionException::conversionFailedFormat(
                    $value,
                    $this->getName(),
                    'Y'
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
        throw ConversionException::conversionFailedInvalidType(
            $value,
            $this->getName(),
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
        } catch (Exception) {
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
