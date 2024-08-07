<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types;

use ArrayAccess\TrayDigita\Util\Filter\DataType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;
use function is_resource;
use function stream_get_contents;

class Data extends TextType
{
    public const NAME = 'data';

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return DataType::shouldUnSerialize($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        $value = is_resource($value) ? stream_get_contents($value) : $value;
        return DataType::shouldSerialize($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
