<?php
/** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types;

use ArrayAccess\TrayDigita\Database\Types\Converters\DatetimeConversion;
use ArrayAccess\TrayDigita\Database\Types\Converters\DatetimeImmutableConversion;
use ArrayAccess\TrayDigita\Database\Types\Converters\DateConversionTrait;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use BadMethodCallException;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\PhpDateTimeMappingType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Throwable;

/**
 * Wrapper for fix 0000-00-00 date issue.
 */
class DateTypeWrapper extends Type implements PhpDateTimeMappingType
{
    use DateConversionTrait;

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
        return $this->convertDateString($value);
    }

    /**
     * @inheritdoc
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if (isset($this->wrappedType)) {
            $date = $this->wrappedType->convertToPHPValue($value, $platform);
        } else {
            $date = parent::convertToPHPValue($value, $platform);
        }
        if ($date instanceof DateTimeImmutable) {
            $date = DatetimeImmutableConversion::createFromInterface($date);
        } elseif ($date instanceof DateTime) {
            $date = DatetimeConversion::createFromInterface($date);
        }
        return $date;
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
