<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Factory;

use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use function is_numeric;
use function ksort;
use function uasort;

class MetadataFactory extends ClassMetadataFactory
{
    /**
     * @inheritdoc
     */
    public function getAllMetadata(): array
    {
        $metadata = [];
        foreach (parent::getAllMetadata() as $classMetadata) {
            if (!isset($classMetadata->table['options']['priority'])) {
                $classMetadata->table['options']['priority'] = 10;
            }
            $metadata[$classMetadata->table['name']] = $classMetadata;
        }
        ksort($metadata);
        uasort(
            $metadata,
            function (ClassMetadataInfo $a, ClassMetadataInfo $b) {
                $a = $a->table['options']['priority'];
                $b = $b->table['options']['priority'];
                if ($a === $b || !is_numeric($a) || !is_numeric($b)) {
                    return 0;
                }
                return $a < $b ? -1 : 1;
            }
        );
        return $metadata;
    }
}
