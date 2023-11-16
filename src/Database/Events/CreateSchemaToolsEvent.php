<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Events;

use ArrayAccess\TrayDigita\Database\Attributes\SubscribeEvent;
use ArrayAccess\TrayDigita\Database\DatabaseEvent;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Throwable;
use function array_change_key_case;
use function array_combine;
use function array_map;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Resolve relation schema for naming conversion & relation action
 */
#[SubscribeEvent]
class CreateSchemaToolsEvent extends DatabaseEvent implements EventSubscriber
{
    public const DOCTRINE_VERSION = 2;

    protected function getManager() : ?ManagerInterface
    {
        return ContainerHelper::use(
            ManagerInterface::class,
            $this->getConnection()->getContainer()
        );
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs): void
    {
        $manager = $this->getManager();
        $manager
            ?->dispatch(
                'database.beforeSchemaEventPostGenerateSchemaTable',
                $eventArgs
            );
        try {
            $table = $eventArgs->getClassTable();
            foreach ($table->getColumns() as $column) {
                $attributes = $column->toArray();
                if (!isset($attributes['attribute'])) {
                    continue;
                }
                try {
                    $definition = $this->getColumnDeclarationSQLOnUpdate(
                        $column
                    );
                    if ($definition) {
                        $column->setColumnDefinition($definition);
                    }
                } catch (Throwable) {
                }
            }

            $foreignKeys = $table->getForeignKeys();
            $associations = $eventArgs->getClassMetadata()->getAssociationMappings();
            $allowedTrigger = ['CASCADE', 'NO ACTION', 'RESTRICT', 'SET NULL'];
            foreach ($associations as $association) {
                if (empty($association['joinColumns'])
                    || empty($association['targetToSourceKeyColumns'])
                    || !is_array($association['targetToSourceKeyColumns'])
                ) {
                    continue;
                }

                $columns = array_change_key_case(
                    array_map('strtolower', $association['targetToSourceKeyColumns'])
                );

                $onUpdate = null;
                $onDelete = null;
                $relationName = null;
                foreach ($association['joinColumns'] as $item) {
                    $options = $item['options'] ?? null;
                    if (!$options) {
                        continue;
                    }
                    $options = array_change_key_case($options);
                    if (!$relationName && isset($options['relation_name'])
                        && is_string($options['relation_name'])
                    ) {
                        $relation = trim($options['relation_name']);
                        if ($relation !== '') {
                            $relationName = $relation;
                        }
                    }
                    if (!$onUpdate) {
                        $update = $options['onupdate'] ?? null;
                        /** @noinspection DuplicatedCode */
                        $update = is_string($update)
                            ? strtoupper(str_replace('  ', '', $update))
                            : null;
                        if ($update === 'SETNULL'
                            || $update === 'SET-NULL'
                            || $update === 'SET_NULL'
                            || $update === 'NULL'
                        ) {
                            $update = 'SET NULL';
                        }
                        if ($update === 'NOACTION'
                            || $update === 'NO-ACTION'
                            || $update === 'NO_ACTION'
                        ) {
                            $update = 'NO ACTION';
                        }
                        if ($update && in_array($update, $allowedTrigger)) {
                            $onUpdate = $update;
                        }
                    }
                    if (!$onDelete) {
                        $delete = $options['ondelete'] ?? null;
                        /** @noinspection DuplicatedCode */
                        $delete = is_string($delete)
                            ? strtoupper(str_replace('  ', '', $delete))
                            : null;
                        // resolve
                        if ($delete === 'SETNULL'
                            || $delete === 'SET-NULL'
                            || $delete === 'SET_NULL'
                            || $delete === 'NULL'
                        ) {
                            $delete = 'SET NULL';
                        }
                        if ($delete === 'NOACTION'
                            || $delete === 'NO-ACTION'
                            || $delete === 'NO_ACTION'
                        ) {
                            $delete = 'NO ACTION';
                        }
                        if ($delete && in_array($delete, $allowedTrigger)) {
                            $onDelete = $delete;
                        }
                    }
                }

                $validName = is_string($relationName) && $relationName !== '';
                $targetTable = $this
                    ->connection
                    ->getEntityManager()
                    ->getClassMetadata($association['targetEntity'])
                    ->getTableName();
                $removedForeign = [];
                foreach ($foreignKeys as $foreignName => $foreignKey) {
                    $name = is_string($foreignName) ? $foreignName : $foreignKey->getName();
                    if (!str_starts_with(strtolower($name), 'fk_')
                        || $foreignKey->getForeignTableName() !== $targetTable
                    ) {
                        continue;
                    }
                    $keys = array_map('strtolower', $foreignKey->getForeignColumns());
                    $values = array_map('strtolower', $foreignKey->getLocalColumns());
                    $dataColumns = array_combine($keys, $values);
                    foreach ($columns as $key => $v) {
                        if (!isset($dataColumns[$key]) || $dataColumns[$key] !== $v) {
                            continue 2;
                        }
                    }

                    /**
                     * Resolve for onUpdate & onDelete
                     */
                    $opt = $foreignKey->getOptions();
                    if ($onUpdate) {
                        $opt['onUpdate'] = $onUpdate;
                    }
                    if ($onDelete && empty($opt['onDelete'])) {
                        $opt['onDelete'] = $onDelete;
                    }
                    try {
                        $opt['oldName'] = $foreignName;
                        $validName = $validName ? $relationName : $foreignName;
                        $table->removeForeignKey($foreignName);
                        $removedForeign[$name] = $validName;
                        $table->addForeignKeyConstraint(
                            $foreignKey->getForeignTableName(),
                            $foreignKey->getLocalColumns(),
                            $foreignKey->getForeignColumns(),
                            $opt,
                            $validName
                        );
                    } catch (Throwable) {
                    }
                }
            }
            $manager
                ?->dispatch(
                    'database.schemaEventPostGenerateSchemaTable',
                    $eventArgs
                );
        } finally {
            $manager
                ?->dispatch(
                    'database.afterSchemaEventPostGenerateSchemaTable',
                    $eventArgs
                );
        }
    }

    /**
     * @param Column $column
     * @return string|null
     * @throws Exception
     * @see \Doctrine\DBAL\Platforms\AbstractPlatform::getColumnDeclarationSQL()
     */
    protected function getColumnDeclarationSQLOnUpdate(Column $column): ?string
    {
        if ($column->getColumnDefinition() !== null) {
            return null;
        }

        $columnAttributes = $column->toArray();
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            return null;
        }

        $attribute = '';
        if (!empty($columnAttributes['attribute'])) {
            $attr = $this->getColumnDeclarationAttributeOnUpdate(
                $columnAttributes['attribute'],
                $columnAttributes
            );
            $attribute = $attr ? " $attr" : '';
        }
        if ($attribute === '') {
            return null;
        }

        $default = $platform->getDefaultValueDeclarationSQL($columnAttributes);

        $charset = ! empty($columnAttributes['charset']) ?
            ' ' . $platform->getColumnCharsetDeclarationSQL($columnAttributes['charset']) : '';

        $collation = ! empty($columnAttributes['collation']) ?
            ' ' . $platform->getColumnCollationDeclarationSQL($columnAttributes['collation']) : '';
        $notnull = $column->getNotnull() ? ' NOT NULL' : '';
        $unique = '';
        $check = '';
        $type = $column->getType();
        $typeDecl    = $type->getSQLDeclaration($columnAttributes, $platform);
        $declaration = $typeDecl . $attribute . $charset . $default . $notnull . $unique . $check . $collation;
        if ($platform->supportsInlineColumnComments()) {
            $comment = $column->getComment();
            if ($type instanceof DateTimeType
                || $type instanceof TimeType
                || $type instanceof JsonType
                || $type instanceof SimpleArrayType
            ) {
                $comment = sprintf(
                    '%s(%s:%s)',
                    $comment??'',
                    $this->getDoctrineConversionType(),
                    $columnAttributes['type']->getName()
                );
                // $column->setComment($comment);
            }
            if ($comment !== null) {
                $declaration .= ' ' . $platform->getInlineColumnCommentSQL($comment);
            }
        }

        return $declaration;
    }

    private function getColumnDeclarationAttributeOnUpdate(
        $attribute,
        array $attributes
    ) : string {
        if (!is_string($attribute) || !isset($attributes['type'])
            || ! $attributes['type'] instanceof Type
        ) {
            return '';
        }
        $attribute = strtoupper(trim($attribute));
        if ($attribute === '') {
            return '';
        }
        if (str_starts_with(
            str_replace(' ', '', $attribute),
            'ON_UPDATE_CURRENT_TIMESTAMP'
        )
        ) {
            $attribute = 'ON UPDATE CURRENT_TIMESTAMP';
        } elseif (str_starts_with(
            str_replace(' ', '', $attribute),
            'UNSIGNED_ZEROFILL'
        )
        ) {
            $attribute = 'UNSIGNED ZEROFILL';
        }
        $allowedAttributes = [
            'ON UPDATE CURRENT_TIMESTAMP' => [
                DateTimeType::class,
                TimeType::class
            ],
            'COMPRESSED=ZLIB' => [
                BlobType::class,
                StringType::class,
                BinaryType::class,
            ],
            'UNSIGNED ZEROFILL' => [
                BigIntType::class,
                SmallIntType::class,
                IntegerType::class,
                DecimalType::class,
                FloatType::class,
            ],
            'UNSIGNED' => [
                BigIntType::class,
                SmallIntType::class,
                IntegerType::class,
                DecimalType::class,
                FloatType::class
            ],
        ];
        $current = $allowedAttributes[$attribute]??null;
        if (!$current) {
            return '';
        }

        foreach ($current as $className) {
            if (is_a($attributes['type'], $className)) {
                return $attribute === 'COMPRESSED=ZLIB'
                    ? 'COMPRESSED=zlib'
                    : $attribute;
            }
        }
        return '';
    }

    public function getDoctrineConversionType(): string
    {
        $manager = $this->getManager();
        $version = self::DOCTRINE_VERSION;
        $defaultType = "DC{$version}Type";

        // @dispatch(doctrineSchema.conversionType)
        $type = $manager?->dispatch('database.schemaConversionType', $defaultType);
        if (is_string($type) &&
            preg_match('~^DC([1-4])Type$~i', $type, $match)
            || is_int($type)
        ) {
            $version = is_int($type) ? $type : ($match[1]??$version);
        }

        return "DC{$version}Type";
    }

    public function getSubscribedEvents() : array
    {
        return [
            ToolEvents::postGenerateSchemaTable
        ];
    }
}
