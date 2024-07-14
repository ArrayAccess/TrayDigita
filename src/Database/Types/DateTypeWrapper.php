<?php
/** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use BadMethodCallException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\PhpDateTimeMappingType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Throwable;

/**
 * Wrapper for fix 0000-00-00 date issue.
 */
class DateTypeWrapper extends Type
{
    /**
     * Wrapped type of DateType.
     *
     * @var DateType|PhpDateTimeMappingType $wrappedType
     */
    private DateType|PhpDateTimeMappingType $wrappedType;

    /**
     * @template T instanceof DateType
     * @param T $dateType
     * @return T
     */
    public static function wrapType($dateType)
    {
        /** @noinspection PhpInternalEntityUsedInspection */
        if (!$dateType instanceof DateType && !$dateType instanceof PhpDateTimeMappingType) {
            throw new InvalidArgumentException('The type must be an instance of DateType');
        }
        $date = new self();
        $date->wrappedType = $dateType;
        return $date;
    }

    /**
     * @return DateType|PhpDateTimeMappingType|null
     */
    public function getWrappedType(): DateType|PhpDateTimeMappingType|null
    {
        return $this->wrappedType??null;
    }

    /**
     * @return string The name of this type.
     */
    public function getName(): string
    {
        try {
            $wrappedType = $this->getWrappedType();
            return $wrappedType ? Type::getTypeRegistry()->lookupName($wrappedType) : Types::DATE_MUTABLE;
        } catch (Throwable) {
            return Types::DATE_MUTABLE;
        }
    }

    /**
     * @inheritdoc
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform) : ?string
    {
        if (isset($this->wrappedType)) {
            $value = $this->wrappedType->convertToDatabaseValue($value, $platform);
        } else {
            $value = parent::convertToDatabaseValue($value, $platform);
        }
        if ($value && is_string($value) && str_starts_with($value, '-000')) {
            $value = preg_replace_callback(
                '/^-(0001)([^0-9]+)?(?:(0[0-9]|1[1-2])([^0-9]+)([1-2][0-9]|3[0-1]))?/',
                static function ($match) {
                    if ($match[0] === '0001-11-30 00:00:00') {
                        return '0000-00-00 00:00:00';
                    }
                    if ($match[0] === '0001') {
                        return '0000';
                    }
                    if (str_starts_with($match[0], '0001-11-')) {
                        return substr_replace($match[0], '0000-00-00', 0, 10);
                    }
                    if (str_starts_with($match[0], '0001-11')) {
                        return substr_replace($match[0], '0000-00', 0, 7);
                    }
                    if (!isset($match[3])) {
                        return $match[1] . ($match[2]??'');
                    }
                    if (intval($match[3]) >= 11) {
                        $year = intval($match[1]) + 1;
                        $match[1] = str_pad((string)$year, 4, '0', STR_PAD_LEFT);
                        $match[3] = '00';
                        $match[5] = '00';
                    } else {
                        $month = $match[3];
                        $intMonth = intval($month);
                        $match[3] = str_pad((string)($intMonth + 1), 2, '0', STR_PAD_LEFT);
                        $day = intval($match[5]) + 1;
                        $_31_days_month = [1, 3, 5, 7, 8, 10, 12];
                        if (in_array($intMonth, $_31_days_month, true) && $day > 31) {
                            $match[5] = '01';
                        } elseif ($intMonth === 2 && $day > 29) {
                            $match[5] = '01';
                        } elseif ($day > 30) {
                            $match[5] = '01';
                        } else {
                            $match[5] = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    return $match[1] . $match[2] . $match[3] . $match[4] . $match[5];
                },
                $value
            );
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if (isset($this->wrappedType)) {
            return $this->wrappedType->convertToPHPValue($value, $platform);
        }
        return parent::convertToPHPValue($value, $platform);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (isset($this->wrappedType)) {
            return $this->wrappedType->getSQLDeclaration($column, $platform);
        }
        return 'DATE';
    }

    public function __call(string $name, array $arguments)
    {
        if (isset($this->wrappedType)) {
            return $this->wrappedType->{$name}(...$arguments);
        }
        throw new BadMethodCallException(
            sprintf('Call to undefined method %s::%s()', static::class, $name)
        );
    }
}
