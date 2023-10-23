<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Traits;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Throwable;
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

    private function createStaticCache(
        ClassMetadataInfo $metadata
    ): void {
        $reflection = $metadata->getReflectionClass();
        $className = $reflection->getName();
        if (!isset(self::$columnsFields[$className])) {
            self::$columnsFields[$className] = [];
            self::$objectFields[$className] = [];
            foreach ($metadata->getFieldNames() as $item) {
                try {
                    $mapping = $metadata->getFieldMapping($item);
                } catch (Throwable) {
                    continue;
                }
                /** @noinspection DuplicatedCode */
                $fieldName = $mapping['fieldName'] ?? null;
                $columnName = $mapping['columnName'] ?? null;
                if (!$fieldName || !$columnName) {
                    continue;
                }
                $columnName = strtolower($columnName);
                self::$objectFields[$className][$columnName] = $fieldName;
                self::$columnsFields[$className][$columnName] = $fieldName;
            }
            foreach ($metadata->getAssociationMappings() as $associationMapping) {
                $fieldName = $associationMapping['fieldName'] ?? null;
                if (!is_string($fieldName)
                    || !$reflection->hasProperty($fieldName)
                    || $reflection->getProperty($fieldName)->isPrivate()
                ) {
                    continue;
                }
                $lower = strtolower($fieldName);
                self::$objectFields[$className][$lower] ??= $reflection->getProperty($fieldName)->getName();
            }
        }

        if (!isset(self::$objectMethodIsGetFields[$className])) {
            $getMethods = [];
            $setMethods = [];
            foreach ($reflection->getMethods() as $method) {
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

    private function configureMethodsParameterFields(): void
    {
        $className = $this::class;
        if (isset(self::$objectFields[$className])) {
            return;
        }
        $em = $this->getEntityManager()
            ?? ContainerHelper::service(Connection::class)->getEntityManager();
        $this->createStaticCache($em->getClassMetadata($className));
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
