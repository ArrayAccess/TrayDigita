<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Logger\Handler;

use ArrayAccess\TrayDigita\Database\Connection;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Types;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

class DatabaseHandler extends AbstractProcessingHandler
{
    protected DoctrineConnection $connection;

    protected string $tableName = 'log_items';

    protected string $idColumn = 'id';
    protected string $channelColumn = 'channel';
    protected string $levelColumn = 'level';
    protected string $messageColumn = 'message';

    protected string $createdTime = 'created_time';

    private bool $initSchema = false;

    public function __construct(
        DoctrineConnection|Connection $connection,
        array $options = [],
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->connection = $connection instanceof Connection
            ? $connection->getConnection()
            : $connection;
        $tableName = $options['db_table']
            ??$options['dbtable']
            ??$options['table']
            ??$this->tableName;
        if (!is_string($tableName) || trim($tableName) === '') {
            $tableName = $this->tableName;
        }
        $tableName = strtolower($tableName);
        if (preg_match('~[^a-z0-9_]~', $tableName)) {
            $tableName = $this->tableName;
        }

        $this->tableName = trim($tableName);
    }

    /**
     * @throws Exception
     */
    public function createTable(): void
    {
        $schema = new Schema();
        $this->addTableToSchema($schema);

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->tableName);
        $table->addColumn(
            $this->idColumn,
            Types::BIGINT,
            [
                'autoincrement' => true,
                'length' => 20
            ]
        );
        $table->addColumn(
            $this->channelColumn,
            Types::STRING,
            [
                'default' => 'default',
                'length' => 255
            ]
        );
        $table->addColumn(
            $this->levelColumn,
            Types::STRING,
            [
                'length' => 10
            ]
        );
        $table->addColumn(
            $this->messageColumn,
            Types::BLOB,
            [
                'length' => AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB
            ]
        );
        $table->addColumn(
            $this->createdTime,
            Types::INTEGER,
            [
                'length' => 10,
                'unsigned' => true,
            ]
        );
        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
            // $column->setDefault('CURRENT_TIMESTAMP');
            $table->addOption('charset', 'utf8mb4');
            $table->addOption('collation', 'utf8mb4_unicode_ci');
        }

        $table->addIndex([
            $this->channelColumn,
            $this->levelColumn
        ], 'index_channel_level');
        $table->setPrimaryKey([$this->idColumn]);
        $table->setComment('Record for logs');
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    protected function configureSchema(): void
    {
        if ($this->initSchema) {
            return;
        }
        $this->initSchema = true;
        $schemaManager = $this->connection->createSchemaManager();
        $schema = new Schema();
        $this->addTableToSchema($schema);
        if ($schemaManager->tablesExist($this->tableName)) {
            $comparator = $schemaManager->createComparator();
            $table = $schema->getTable($this->tableName);
            $tableDiff = $comparator->compareTables(
                $schemaManager->introspectTable($this->tableName),
                $table
            );
            foreach ($tableDiff->removedColumns as $key => $column) {
                // do not remove column
                if (!$table->hasColumn($column->getName())) {
                    unset($tableDiff->removedColumns[$key]);
                }
            }
            foreach ($tableDiff->changedColumns as $key => $column) {
                if (!$column->hasDefaultChanged()
                    && !$column->hasTypeChanged()
                    && !$column->hasScaleChanged()
                    && !$column->hasNotNullChanged()
                    && !$column->hasLengthChanged()
                    && !$column->hasAutoIncrementChanged()
                    && !$column->hasFixedChanged()
                    && !$column->hasPrecisionChanged()
                    && !$column->hasUnsignedChanged()
                ) {
                    unset($tableDiff->changedColumns[$key]);
                }
            }

            $sqlList = $this
                ->connection
                ->getDatabasePlatform()
                ->getAlterTableSQL(
                    $tableDiff
                );
        } else {
            $sqlList = $schema->toSql($this->connection->getDatabasePlatform());
        }

        if (empty($sqlList)) {
            return;
        }
        foreach ($sqlList as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getIdColumn(): string
    {
        return $this->idColumn;
    }

    public function getChannelColumn(): string
    {
        return $this->channelColumn;
    }

    public function getLevelColumn(): string
    {
        return $this->levelColumn;
    }

    public function getMessageColumn(): string
    {
        return $this->messageColumn;
    }

    public function getCreatedTime(): string
    {
        return $this->createdTime;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @throws Exception
     * @throws SchemaException
     */
    protected function write(LogRecord $record): void
    {
        $this->configureSchema();
        $connection = $this->connection;
        $connection
            ->createQueryBuilder()
            ->insert($this->tableName)
            ->values([
                $this->channelColumn => '?',
                $this->levelColumn => '?',
                $this->messageColumn => '?',
                $this->createdTime => '?',
            ])
            ->setParameters([
                $record->channel,
                $record->level->getName(),
                $record->message,
                $record->datetime->getTimestamp()
            ])->executeQuery();
    }
}
