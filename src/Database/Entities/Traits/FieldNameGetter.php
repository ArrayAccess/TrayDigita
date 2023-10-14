<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Traits;

use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappingAttribute;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use ReflectionAttribute;
use ReflectionObject;
use function array_diff;
use function array_map;
use function in_array;
use function is_string;
use function str_replace;
use function strtolower;
use function substr;

trait FieldNameGetter
{
    private static array $objectFields = [];

    private static array $columnsFields = [];

    private static array $objectMethodIsGetFields = [];

    // reference for set
    private static array $objectMethodSetFields = [];

    private function configureMethodsParameterFields(): void
    {
        $className = $this::class;
        if (!isset(self::$objectFields[$className])) {
            /** @noinspection PhpInstanceofIsAlwaysTrueInspection */
            $isAbstractEntity = $this instanceof AbstractEntity;
            self::$objectFields[$className] = [];
            self::$columnsFields[$className] = [];
            $ref = new ReflectionObject($this);
            $prop = [];
            $allowedMapping = [
                Id::class,
                JoinColumn::class,
                ManyToOne::class,
                ManyToMany::class,
                OneToOne::class,
                OneToMany::class,
                Column::class,
                JoinTable::class,
            ];
            $countAllowedMapping = count($allowedMapping);
            foreach ($ref->getProperties() as $property) {
                if ($property->isPrivate()) {
                    continue;
                }
                $attr = array_map(fn ($e) => $e->getname(), $property
                    ->getAttributes(
                        MappingAttribute::class,
                        ReflectionAttribute::IS_INSTANCEOF
                    ));
                if (empty($attr)) {
                    continue;
                }
                $count = array_diff($allowedMapping, $attr);
                if (count($count) === $countAllowedMapping) {
                    continue;
                }
                $name = $property->getName();
                $key = strtolower($name);
                $prop[$key] = $name;
            }

            if ($isAbstractEntity
                && ($metadata = $this
                    ->getEntityManager()
                    ?->getClassMetadata($this::class))
            ) {
                $prop = [];
                foreach ($metadata->getFieldNames() as $item) {
                    $mapping = $metadata->getFieldMapping($item);
                    $fieldName = $mapping['fieldName']??null;
                    $columnName = $mapping['columnName']??null;
                    if (!$fieldName || !$columnName) {
                        continue;
                    }
                    $columnName = strtolower($columnName);
                    $prop[$columnName] = $fieldName;
                    self::$columnsFields[$className][$columnName] = $fieldName;
                }

                foreach ($metadata->getAssociationMappings() as $associationMapping) {
                    $fieldName = $associationMapping['fieldName']??null;
                    if (!is_string($fieldName)
                        || !$ref->hasProperty($fieldName)
                        || $ref->getProperty($fieldName)->isPrivate()
                    ) {
                        continue;
                    }

                    $lower = strtolower($fieldName);
                    $prop[$lower] ??= $ref->getProperty($fieldName)->getName();
                }
            }

            self::$objectFields[$className] = $prop;
        }

        if (!isset(self::$objectMethodIsGetFields[$className])
            || !isset(self::$objectMethodSetFields[$className])
        ) {
            $getMethods = [];
            $setMethods = [];
            $ref ??= new ReflectionObject($this);
            foreach ($ref->getMethods() as $method) {
                if (!$method->isPublic()
                    || $method->getNumberOfRequiredParameters() > 0
                ) {
                    continue;
                }

                $methodName = $method->getName();
                $lowerMethodName = strtolower($methodName);
                $substrStart = str_starts_with($lowerMethodName, 'get')
                    ? 3
                    : (str_starts_with($lowerMethodName, 'is') ? 2 : null);
                if ($substrStart !== null) {
                    $lowerMethodName = substr($lowerMethodName, $substrStart);
                    $getMethods[$lowerMethodName] = $methodName;
                    continue;
                }
                if ($method->getNumberOfRequiredParameters() > 1
                    || !str_starts_with($lowerMethodName, 'set')
                ) {
                    continue;
                }
                $setMethods[$lowerMethodName] = $methodName;
            }
            self::$objectMethodIsGetFields[$className] = $getMethods;
            self::$objectMethodSetFields[$className] = $setMethods;
        }
    }

    public function get(string $name, &$found = null)
    {
        $found = false;
        $className = $this::class;
        $this->configureMethodsParameterFields();
        $lower = strtolower($name);
        if (!isset(self::$objectFields[$className][$lower])) {
            return null;
        }

        if (isset(self::$objectMethodIsGetFields[$className][$lower])) {
            $found = true;
            return $this->{self::$objectMethodIsGetFields[$className][$lower]}();
        }

        $nextLower = str_replace('_', '', $lower);
        if ($nextLower !== $lower
            && isset(self::$objectMethodIsGetFields[$className][$nextLower])
        ) {
            $found = true;
            return $this->{self::$objectMethodIsGetFields[$className][$nextLower]}();
        }

        $field = self::$objectFields[$className][$lower]??null;
        if ($field === null && in_array($name, self::$objectFields[$className])) {
            $field = $name;
        }

        $found = $field !== null;
        return $field ? $this->$field : null;
    }

    /**
     * Magic method field name getter
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __isset(string $name) : bool
    {
        $this->get($name, $found);
        return $found === true;
    }
}
