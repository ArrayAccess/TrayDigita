<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class DataBlob extends Data
{
    public const NAME = 'data_blob';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
