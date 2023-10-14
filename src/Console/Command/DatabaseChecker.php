<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Console\Application;
use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InteractiveArgumentException;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\SqlFormatter\CliHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use PDO;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use function array_change_key_case;
use function array_filter;
use function array_map;
use function end;
use function explode;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function max;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function preg_replace;
use function round;
use function rtrim;
use function sprintf;
use function strtolower;
use function trim;
use const ARRAY_FILTER_USE_KEY;
use const PHP_BINARY;

class DatabaseChecker extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        WriterHelperTrait,
        TranslatorTrait;

    private ?EntityManager $entityManager = null;

    protected ?string $space = null;

    private string $printCommand = 'print';
    private string $schemaCommand = 'schema';
    private string $dumpCommand = 'dump';
    private string $executeCommand = 'execute';
    private string $optimizeCommand = 'optimize';

    protected function configure(): void
    {
        $this
            ->setName('app:db')
            ->setAliases(['app:database'])
            ->setDescription(
                $this->translate('Check & validate database.')
            )
            ->setDefinition([
                new InputOption(
                    $this->schemaCommand,
                    's',
                    InputOption::VALUE_NONE,
                    $this->translate('Validate changed / difference about database schema'),
                    null
                ),
                new InputOption(
                    $this->printCommand,
                    'p',
                    InputOption::VALUE_NONE,
                    sprintf(
                        $this->translate('Print sql query create schema (should execute with %s command)'),
                        sprintf(
                            '<info>--%s</info>',
                            $this->schemaCommand
                        )
                    ),
                    null
                ),
                new InputOption(
                    $this->dumpCommand,
                    'd',
                    InputOption::VALUE_NONE,
                    sprintf(
                        $this->translate(
                            'Dump create schema (should execute with %s & %s command)'
                        ),
                        sprintf(
                            '<info>--%s</info>',
                            $this->printCommand
                        ),
                        sprintf(
                            '<info>--%s</info>',
                            $this->schemaCommand
                        )
                    ),
                    null
                ),
                new InputOption(
                    $this->executeCommand,
                    'x',
                    InputOption::VALUE_NONE,
                    sprintf(
                        $this->translate(
                            'Execute query sql that from new schema into database (should execute with %s command)'
                        ),
                        sprintf(
                            '<info>--%s</info>',
                            $this->schemaCommand
                        )
                    ),
                    null
                ),
                new InputOption(
                    $this->optimizeCommand,
                    'o',
                    InputOption::VALUE_NONE,
                    sprintf(
                        $this->translate(
                            'Optimize database when possible (should execute with %s command)'
                        ),
                        sprintf(
                            '<info>--%s</info>',
                            $this->schemaCommand
                        )
                    ),
                    null
                ),
            ])
            ->setHelp(
                sprintf(
                    $this->translate(<<<'EOT'
The %s help you to validate database.
This command show information about installed database & configuration services.
EOT),
                    '<info>%command.name%</info>'
                )
            );
    }

    private array $blackListOptimize = [];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // no execute
        if ($output->isQuiet() || !Consolidation::isCli()) {
            return self::SUCCESS;
        }
        $startTime = microtime(true);
        $memory = memory_get_usage();
        $manager = $this->getManager();
        $manager = ! $manager && $this->getContainer()->has(ManagerInterface::class)
            ? $this->getContainer()->get(ManagerInterface::class)
            : $manager;
        if ($manager instanceof ManagerInterface) {
            $this->setManager($manager);
        }

        $blacklistOptimized = $this->getManager()
            ?->dispatch('console.databaseBlackListOptimize', []);
        $blacklistOptimized = is_array($blacklistOptimized) ? $blacklistOptimized : [];
        $this->blackListOptimize = array_map(
            'strtolower',
            array_filter($blacklistOptimized, 'is_string', ARRAY_FILTER_USE_KEY)
        );
        $theSchema = $input->getOption($this->schemaCommand);
        $thePrint = $input->getOption($this->printCommand);
        $dump = $input->getOption($this->dumpCommand);
        $optionSchemaDump = $dump && $thePrint;
        $optionPrintSchema = !$dump && $thePrint;
        try {
            if ($theSchema) {
                $this->databaseSchemaDetect($input, $output);
            } else {
                $this->doDatabaseCheck($output, false);
            }
            $output->writeln('');
        } finally {
            if ($optionSchemaDump || $optionPrintSchema) {
                return self::SUCCESS;
            }
            $output->writeln('');
            $output->writeln(
                sprintf(
                    'Time %s secs; Memory Usage: %s; Memory Peak Usage: %s',
                    round(microtime(true) - $startTime, 3),
                    Consolidation::sizeFormat(
                        max(memory_get_usage() - $memory, 0),
                        3
                    ),
                    Consolidation::sizeFormat(memory_get_peak_usage(), 3)
                )
            );
            $output->writeln('');
        }

        return self::SUCCESS;
    }

    public function databaseSchemaDetect(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->getContainer();
        if (!$container?->has(Connection::class)) {
            $this->writeDanger(
                $output,
                $this->translate(
                    'Can not get database object from container'
                )
            );
            return Command::FAILURE;
        }
        $database = $container->get(Connection::class);
        if (!$database instanceof Connection) {
            $this->writeDanger(
                $output,
                $this->translate(
                    'Database connection is not valid object from container'
                )
            );
            return Command::FAILURE;
        }
        if (!$this->entityManager) {
            $orm  = clone $database->getEntityManager()->getConfiguration();
            $cache = new ArrayAdapter();
            $orm->setMetadataCache($cache);
            $orm->setResultCache($cache);
            $orm->setHydrationCache($cache);
            $orm->setQueryCache($cache);
            $this->entityManager = new EntityManager(
                $database->getConnection(),
                $orm,
                $database->getEntityManager()->getEventManager()
            );
        }

        $isExecute = $input->getOption($this->executeCommand);
        $thePrint = $input->getOption($this->printCommand);
        $dump = $input->getOption($this->dumpCommand);
        $optimize = $input->getOption($this->optimizeCommand);
        $optionSchemaDump = $dump && $thePrint;
        $optionPrintSchema = !$dump && $thePrint;
        if (!$thePrint && !$isExecute && !$optimize) {
            $this->doDatabaseCheck($output, true);
        }

        $platform = $database->getDatabasePlatform();
        $schemaManager = $database->createSchemaManager();
        $allMetadata = [];
        try {
            $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        } catch (Throwable $e) {
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>- Metadata</info> <error>[%s]</error>',
                    $e->getMessage()
                ),
                mode: self::MODE_DANGER
            );
        }
        $currentSchema = (new SchemaTool($this->entityManager))
            ->getSchemaFromMetadata($allMetadata);
        $comparator = $schemaManager->createComparator();
        $containChange = false;
        $io = new SymfonyStyle($input, $output);
        $interactive = $input->isInteractive();
        $optimizeArray = [];
        $tableMigration = 'doctrine_migration_versions';
        $app = $this->getApplication();
        if ($app instanceof Application) {
            $metadata = $app
                ->getMigrationConfig()
                ?->getConfiguration()
                ->getMetadataStorageConfiguration();
            /** @noinspection PhpUndefinedClassInspection */
            if ($metadata instanceof TableMetadataStorageConfiguration) {
                $tableMigration = $metadata->getTableName();
            }
        }
        $tableMigration = strtolower($tableMigration);
        if ($platform instanceof AbstractMySQLPlatform) {
            $tablesMeta = [];
            $inQuestionMark = [];
            foreach ($currentSchema->getTables() as $tableCurrent) {
                if (!$schemaManager->tablesExist($tableCurrent->getName())) {
                    continue;
                }
                $tablesMeta[] = $schemaManager
                    ->introspectTable($tableCurrent->getName())
                    ->getName();
                $inQuestionMark[] = '?';
            }
            if (!empty($tablesMeta)) {
                foreach ($database
                             ->executeQuery(
                                 sprintf(
                                     'SHOW TABLE STATUS WHERE name in (%s)',
                                     implode(', ', $inQuestionMark)
                                 ),
                                 $tablesMeta
                             )->fetchAllAssociative() as $field) {
                    $field = array_change_key_case($field);
                    $field['name'] = strtolower($field['name']);
                    if (((int)$field['data_free']) < 1
                        || !empty($this->blackListOptimize[$field['name']])
                    ) {
                        continue;
                    }
                    $optimizeArray[$field['name']] = (int)($field['data_free']?:0);
                }
            }
        }

        if ($optionSchemaDump) {
            $sql = $currentSchema->toSql($platform);
            if (empty($sql)) {
                $output->writeln(
                    sprintf(
                        '<info>%s</info>',
                        $this->translate('No schema can be created')
                    )
                );
                return Command::SUCCESS;
            }
            $output->writeln('');
            $formatter = new SqlFormatter(new CliHighlighter());
            foreach ($sql as $query) {
                $query = rtrim($query, ';');
                $output->writeln(
                    $formatter->format("$query;"),
                    OutputInterface::OUTPUT_RAW
                );
            }
            return Command::SUCCESS;
        }
        if ($optionPrintSchema) {
            $clonedSchema = clone $schemaManager->introspectSchema();
            foreach ($clonedSchema->getTables() as $table) {
                if (!$currentSchema->hasTable($table->getName())) {
                    $clonedSchema->dropTable($table->getName());
                }
            }
            $schemaDiff = $comparator->compareSchemas(
                $clonedSchema,
                $currentSchema
            );
            $sql = $platform->getAlterSchemaSQL($schemaDiff);
            if (empty($sql)) {
                $output->writeln(
                    sprintf(
                        '<info>%s</info>',
                        $this->translate('No change into database schema')
                    )
                );
                return Command::SUCCESS;
            }
            $output->writeln('');
            $formatter = new SqlFormatter(new CliHighlighter());
            foreach ($sql as $query) {
                $query = rtrim($query, ';');
                $output->writeln(
                    $formatter->format("$query;"),
                    OutputInterface::OUTPUT_RAW
                );
            }
            return Command::SUCCESS;
        }

        if (!$optimize) {
            $containOptimize = false;
            foreach ($allMetadata as $meta) {
                $tableName = $meta->getTableName();
                $table = $schemaManager->tablesExist($tableName)
                    ? $schemaManager->introspectTable($tableName)
                    : null;
                if (!$table) {
                    $containChange = true;
                    if (!$isExecute) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                $this->translate('Table "%s" does not exist!'),
                                sprintf(
                                    '<comment>%s</comment>',
                                    $tableName
                                )
                            ),
                            mode: self::MODE_DANGER
                        );
                    } else {
                        $this->write(
                            $output,
                            sprintf(
                                $this->translate(
                                    'Table "%s" does not exist!'
                                ),
                                sprintf(
                                    '<comment>%s</comment>',
                                    $tableName
                                )
                            ),
                            mode: self::MODE_DANGER
                        );
                    }
                    continue;
                }
                $currentTable = $currentSchema->getTable($meta->getTableName());
                $tableDiff = $comparator->compareTables($table, $currentTable);
                $isNeedOptimize = ($optimizeArray[strtolower($currentTable->getName())] ?? 0) > 0;
                if ($isNeedOptimize) {
                    $containOptimize = true;
                }
                if ($tableDiff->isEmpty()) {
                    if (!$isExecute) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                $this->translate(
                                    'Table "%s" %s'
                                ),
                                sprintf(
                                    '<comment>%s</comment>',
                                    $tableName
                                ),
                                sprintf(
                                    '%s%s%s',
                                    sprintf(
                                        '<info>%s</info>',
                                        $this->translate('no difference')
                                    ),
                                    $isNeedOptimize
                                    ? sprintf(
                                        ' <comment>[%s]</comment>',
                                        $this->translate('NEED TO OPTIMIZE')
                                    ) : '',
                                    ($currentTable->getComment()
                                        ? sprintf(' <fg=gray>(%s)</>', $currentTable->getComment())
                                        : ''
                                    )
                                )
                            ),
                            mode: self::MODE_SUCCESS
                        );
                    }
                    continue;
                }
                $containChange = true;
                $message = sprintf(
                    '%s%s',
                    sprintf(
                        $this->translate('Table "%s" need to be change'),
                        sprintf(
                            '<comment>%s</comment>',
                            $tableName,
                        )
                    ),
                    $isNeedOptimize ? sprintf(
                        ' <comment>[%s]</comment>',
                        $this->translate('NEED TO OPTIMIZE')
                    ) : '',
                );
                if (!$isExecute) {
                    $this->writeIndent(
                        $output,
                        $message,
                        mode: self::MODE_WARNING
                    );
                } else {
                    $this->write(
                        $output,
                        $message,
                        mode: self::MODE_WARNING
                    );
                }

                $typeRegistry = Type::getTypeRegistry();

                // COLUMNS
                // modified
                foreach ($tableDiff->getModifiedColumns() as $column) {
                    $oldColumn = $column->getOldColumn();
                    $newColumn = $column->getNewColumn();
                    if ($column->hasTypeChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                $this->translate('type'),
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $typeRegistry->lookupName($oldColumn->getType())
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $typeRegistry->lookupName($newColumn->getType())
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasDefaultChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                $this->translate('default value'),
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getDefault() ?? '<empty>'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getDefault() ?? '<empty>'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasFixedChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                'fixed',
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getFixed() ? 'YES' : 'NO'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getFixed() ? 'YES' : 'NO'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasAutoIncrementChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                'auto increment',
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getAutoincrement() ? 'YES' : 'NO'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getAutoincrement() ? 'YES' : 'NO'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasLengthChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                'length',
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getLength() ?? '<empty>'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getLength() ?? '<empty>'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasPrecisionChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                'precision',
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getPrecision() ?? '<empty>'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getPrecision() ?? '<empty>'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasNotNullChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                'not null',
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        !$oldColumn->getNotnull() ? 'YES' : 'NOT'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        !$newColumn->getNotnull() ? 'YES' : 'NOT'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasScaleChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                $this->translate('scale'),
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getScale() ??'<empty>'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getScale() ??'<empty>'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasUnsignedChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                'unsigned',
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getUnsigned() ? 'YES' : 'NO'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getUnsigned() ? 'YES' : 'NO'
                                    )
                                )
                            )
                        );
                    }
                    if ($column->hasCommentChanged()) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '<info>- %s</info> <comment>%s</comment> <info>%s</info> %s',
                                $this->translate('Column'),
                                $newColumn->getName(),
                                $this->translate('comment'),
                                sprintf(
                                    $this->translate('change from: [%s] to [%s]'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $oldColumn->getComment() ?? '<empty>'
                                    ),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $newColumn->getComment() ?? '<empty>'
                                    )
                                )
                            )
                        );
                    }
                }

                // added
                foreach ($tableDiff->getAddedColumns() as $column) {
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> [<comment>%s</comment>]',
                            $this->translate('Added Column'),
                            $column->getName()
                        )
                    );
                }
                // remove
                foreach ($tableDiff->getDroppedColumns() as $column) {
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> [<comment>%s</comment>]',
                            $this->translate('Removed Column'),
                            $column->getName()
                        )
                    );
                }
                // rename
                foreach ($tableDiff->getRenamedColumns() as $columnName => $column) {
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> %s',
                            $this->translate('Renamed Column'),
                            sprintf(
                                $this->translate('from [%s] to [%s]'),
                                sprintf(
                                    '<comment>%s</comment>',
                                    $columnName
                                ),
                                sprintf(
                                    '<comment>%s</comment>',
                                    $column->getName()
                                )
                            )
                        )
                    );
                }

                // INDEXES
                // added
                foreach ($tableDiff->getModifiedIndexes() as $indexName => $index) {
                    $indexName = !is_string($indexName) ? $index->getName() : $indexName;
                    $oldIndex = $table->getIndex($indexName);
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> %s',
                            $this->translate('Modify index'),
                            sprintf(
                                'from [%s](%s) to [%s](%s)',
                                sprintf(
                                    '<comment>%s</comment>',
                                    $indexName
                                ),
                                sprintf(
                                    '<comment>%s</comment>',
                                    implode(', ', $oldIndex->getColumns())
                                ),
                                sprintf(
                                    '<comment>%s</comment>',
                                    $index->getName()
                                ),
                                sprintf(
                                    '<comment>%s</comment>',
                                    implode(', ', $index->getColumns())
                                )
                            )
                        )
                    );
                }

                foreach ($tableDiff->getAddedIndexes() as $index) {
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> [<comment>%s</comment>] %s',
                            $this->translate('Added Index'),
                            $index->getName(),
                            sprintf(
                                'with columns [%s]',
                                sprintf(
                                    '<comment>%s</comment>',
                                    implode(', ', $index->getColumns())
                                )
                            )
                        )
                    );
                }
                // dropped
                foreach ($tableDiff->getDroppedIndexes() as $index) {
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> [<comment>%s</comment>] %s',
                            $this->translate('Removed Index'),
                            $index->getName(),
                            sprintf(
                                'contain columns [%s]',
                                sprintf(
                                    '<comment>%s</comment>',
                                    implode(', ', $index->getColumns())
                                )
                            )
                        )
                    );
                }

                // renamed
                foreach ($tableDiff->getRenamedIndexes() as $indexName => $index) {
                    $indexName = !is_string($indexName) ? $index->getName() : $indexName;
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> %s',
                            $this->translate('Renamed index'),
                            sprintf(
                                'from [%s] to [%s]',
                                sprintf('<comment>%s</comment>', $indexName),
                                sprintf('<comment>%s</comment>', $index->getName())
                            )
                        )
                    );
                }

                // RELATIONS
                // added
                foreach ($tableDiff->getModifiedForeignKeys() as $foreignName => $foreignKey) {
                    $foreignName = !is_string($foreignName) ? $foreignKey->getName() : $foreignName;
                    $oldForeign = $table->getForeignKey($foreignName);
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> [<comment>%s</comment>](<comment>%s</comment>)'
                            . ' -> (<comment>%s</comment>)'
                            . ' [<comment>%s</comment>](<comment>%s</comment>) -> (<comment>%s</comment>)%s',
                            $this->translate('Modify ForeignKey'),
                            $foreignName,
                            sprintf(
                                '%s(%s)',
                                $table->getName(),
                                implode(', ', $oldForeign->getLocalColumns())
                            ),
                            sprintf(
                                '%s(%s)',
                                $oldForeign->getForeignTableName(),
                                implode(', ', $oldForeign->getForeignColumns())
                            ),
                            $foreignKey->getName(),
                            sprintf(
                                '%s(%s)',
                                $table->getName(),
                                implode(', ', $foreignKey->getLocalColumns())
                            ),
                            sprintf(
                                '%s(%s)',
                                $foreignKey->getForeignTableName(),
                                implode(', ', $foreignKey->getForeignColumns())
                            ),
                            (
                            $foreignKey->onDelete() || $foreignKey->onUpdate() ? sprintf(
                                '(<comment>%s%s</comment>)',
                                $foreignKey->onUpdate() ? sprintf(
                                    'ON UPDATE %s',
                                    $foreignKey->onUpdate()
                                ) : '',
                                $foreignKey->onDelete() ? sprintf(
                                    ' ON DELETE %s',
                                    $foreignKey->onDelete()
                                ) : ''
                            ) : ''
                            )
                        )
                    );
                }

                foreach ($tableDiff->getAddedForeignKeys() as $foreignKey) {
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> [<comment>%s</comment>](<comment>%s</comment>) '
                            . '-> (<comment>%s</comment>)',
                            $this->translate('Added ForeignKey'),
                            $foreignKey->getName(),
                            sprintf(
                                '%s(%s)',
                                $table->getName(),
                                implode(', ', $foreignKey->getLocalColumns())
                            ),
                            sprintf(
                                '%s(%s)',
                                $foreignKey->getForeignTableName(),
                                implode(', ', $foreignKey->getForeignColumns())
                            ),
                        )
                    );
                }
                // drop
                foreach ($tableDiff->getDroppedForeignKeys() as $foreignKey) {
                    $this->writeIndent(
                        $output,
                        sprintf(
                            '<info>- %s</info> [<comment>%s</comment>](<comment>%s</comment>) '
                            . '-> (<comment>%s</comment>)',
                            $this->translate('Removed ForeignKey'),
                            $foreignKey->getName(),
                            sprintf(
                                '%s(%s)',
                                $table->getName(),
                                implode(', ', $foreignKey->getLocalColumns())
                            ),
                            sprintf(
                                '%s(%s)',
                                $foreignKey->getForeignTableName(),
                                implode(', ', $foreignKey->getForeignColumns())
                            ),
                        )
                    );
                }
            }

            if (!$isExecute) {
                foreach ($schemaManager->introspectSchema()->getTables() as $table) {
                    $tableName = $table->getName();
                    if ($currentSchema->hasTable($tableName)) {
                        continue;
                    }
                    $lowerTableName = strtolower($tableName);
                    if (str_contains($lowerTableName, 'translation')
                        && $table->hasColumn('domain')
                        && $table->hasColumn('language')
                        && $table->hasColumn('original')
                        && $table->hasColumn('translation')
                        && $table->hasColumn('plural_translation')
                    ) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '%s%s',
                                sprintf(
                                    $this->translate('Table "%s" for %s & does not exist in schema'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $table->getName(),
                                    ),
                                    sprintf(
                                        '<info>%s</info>',
                                        'translations'
                                    )
                                ),
                                ($table->getComment()
                                    ? sprintf(' <fg=gray>(%s)</>', $table->getComment())
                                    : ''
                                )
                            ),
                            mode: self::MODE_WARNING
                        );
                        continue;
                    }
                    if (str_contains($lowerTableName, 'cache')
                        && $table->hasColumn('item_id')
                        && $table->hasColumn('item_data')
                        && $table->hasColumn('item_lifetime')
                        && $table->hasColumn('item_time')
                    ) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '%s%s',
                                sprintf(
                                    $this->translate('Table "%s" for %s & does not exist in schema'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $table->getName(),
                                    ),
                                    sprintf(
                                        '<info>%s</info>',
                                        'cache'
                                    )
                                ),
                                ($table->getComment()
                                    ? sprintf(' <fg=gray>(%s)</>', $table->getComment())
                                    : ''
                                )
                            ),
                            mode: self::MODE_WARNING
                        );
                        continue;
                    }

                    if (str_contains($lowerTableName, 'log')
                        && $table->hasColumn('id')
                        && $table->hasColumn('channel')
                        && $table->hasColumn('level')
                        && $table->hasColumn('message')
                    ) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '%s%s',
                                sprintf(
                                    $this->translate('Table "%s" for %s & does not exist in schema'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $table->getName(),
                                    ),
                                    sprintf(
                                        '<info>%s</info>',
                                        'logs'
                                    )
                                ),
                                ($table->getComment()
                                    ? sprintf(' <fg=gray>(%s)</>', $table->getComment())
                                    : ''
                                )
                            ),
                            mode: self::MODE_WARNING
                        );
                        continue;
                    }

                    if ($tableMigration === $lowerTableName) {
                        $this->writeIndent(
                            $output,
                            sprintf(
                                '%s%s',
                                sprintf(
                                    $this->translate('Table "%s" for %s'),
                                    sprintf(
                                        '<comment>%s</comment>',
                                        $table->getName(),
                                    ),
                                    sprintf(
                                        '<info>%s</info>',
                                        'migrations'
                                    )
                                ),
                                ($table->getComment()
                                    ? sprintf(' <fg=gray>(%s)</>', $table->getComment())
                                    : ''
                                )
                            ),
                            mode: self::MODE_SUCCESS
                        );
                        continue;
                    }

                    $this->writeIndent(
                        $output,
                        sprintf(
                            '%s%s',
                            sprintf(
                                'Table "%s" exists in database but not in schema',
                                sprintf(
                                    '<comment>%s</comment>',
                                    $table->getName(),
                                )
                            ),
                            ($table->getComment()
                                ? sprintf(' <fg=gray>(%s)</>', $table->getComment())
                                : ''
                            )
                        ),
                        mode: self::MODE_WARNING
                    );
                }
            }

            if ($containChange && !$isExecute) {
                $output->writeln('');
                $output->writeln(
                    sprintf(
                        '<info>%s</info>',
                        $this->translate('Contains changed database schema, you can execute command :')
                    )
                );
                $output->writeln('');
                $this->writeIndent(
                    $output,
                    sprintf(
                        '<comment>%s %s %s --%s --%s</comment>',
                        PHP_BINARY,
                        $_SERVER['PHP_SELF'],
                        $this->getName(),
                        $this->schemaCommand,
                        $this->executeCommand
                    )
                );
                $output->writeln('');
            }
            if (!$isExecute && $containOptimize) {
                $output->writeln('');
                $output->writeln(
                    sprintf(
                        '<info>%s</info>',
                        $this->translate('Contains database table that can be optimized, you can execute command :')
                    )
                );
                $output->writeln('');
                $this->writeIndent(
                    $output,
                    sprintf(
                        '<comment>%s %s %s --%s --%s</comment>',
                        PHP_BINARY,
                        $_SERVER['PHP_SELF'],
                        $this->getName(),
                        $this->schemaCommand,
                        $this->optimizeCommand
                    )
                );
                $output->writeln('');
            }
            if (!$isExecute) {
                return Command::SUCCESS;
            }
        } else {
            if (empty($optimizeArray)) {
                $output->writeln(
                    sprintf(
                        '<info>%s</info>',
                        $this->translate('There are no tables that can be optimized')
                    )
                );
                return Command::SUCCESS;
            }
            // only mysql
            if (!$platform instanceof AbstractMySQLPlatform) {
                $className = explode('\\', $platform::class);
                $className = end($className);
                $className = preg_replace('~Platform$~', '', $className);
                $output->writeln(
                    sprintf(
                        $this->translate('%s does not yet support optimization'),
                        sprintf(
                            '<comment>%s</comment>',
                            $className
                        )
                    )
                );
                return Command::SUCCESS;
            }
            foreach ($optimizeArray as $tableName => $freed) {
                if (!$currentSchema->hasTable($tableName)) {
                    unset($optimizeArray[$tableName]);
                    continue;
                }
                $table = $currentSchema->getTable($tableName);
                $optimizeArray[$tableName] = $table->getName();
                $output->writeln(
                    sprintf(
                        $this->translate('Table "%s" can be freed up to : (%s)'),
                        sprintf(
                            '<comment>%s</comment>',
                            $table->getName(),
                        ),
                        sprintf(
                            '<info>%s</info>',
                            Consolidation::sizeFormat($freed, 4)
                        )
                    )
                );
            }

            $answer = $interactive ? $io->ask(
                $this->translate('Are you sure to continue (Yes/No)?'),
                null,
                function ($e) {
                    $e = !is_string($e) ? '' : $e;
                    $e = strtolower(trim($e));
                    $ask = match ($e) {
                        'yes' => true,
                        'no' => false,
                        default => null
                    };
                    if ($ask === null) {
                        throw new InteractiveArgumentException(
                            $this->translate('Please enter valid answer! (Yes / No)')
                        );
                    }
                    return $ask;
                }
            ) : true;
            if (!$answer) {
                $output->writeln(
                    sprintf(
                        '<comment>%s</comment>',
                        $this->translate('Operation cancelled!')
                    )
                );
                return Command::SUCCESS;
            }
            $output->writeln('');
            $output->writeln(
                sprintf(
                    '<options=bold><comment>%s</comment></>',
                    $this->translate('PLEASE DO NOT CANCEL OPERATION!')
                )
            );
            $output->writeln('');
            foreach ($optimizeArray as $tableName) {
                $output->write(
                    sprintf(
                        '<info>%s</info> <comment>"%s"</comment>',
                        $this->translate('Optimizing table'),
                        $tableName
                    )
                );
                try {
                    $result = $database
                        ->executeQuery(
                            sprintf(
                                'OPTIMIZE TABLE %s',
                                $database->quoteIdentifier($tableName)
                            )
                        );
                    $status = 'OK';
                    foreach ($result->fetchAllAssociative() as $stat) {
                        $stat = array_change_key_case($stat);
                        if (($stat['msg_type']??null) !== 'status') {
                            continue;
                        }
                        $status = $stat['msg_text']??'OK';
                        break;
                    }
                    $result->free();
                    $output->writeln(
                        sprintf(' [<fg=green>%s</>]', $status)
                    );
                } catch (Throwable $e) {
                    $output->writeln(
                        sprintf(
                            ' [<fg=red>%s</>] <fg=red>%s</>',
                            $this->translate('FAIL'),
                            $e->getMessage()
                        )
                    );
                    continue;
                }
            }
            $output->writeln(
                sprintf(
                    '<info>%s</info>',
                    $this->translate('ALL DONE!')
                )
            );
            return Command::SUCCESS;
        }

        $clonedSchema = clone $schemaManager->introspectSchema();
        foreach ($clonedSchema->getTables() as $table) {
            if (!$currentSchema->hasTable($table->getName())) {
                $clonedSchema->dropTable($table->getName());
            }
        }

        $schemaDiff = $comparator->compareSchemas(
            $clonedSchema,
            $currentSchema
        );

        if ($schemaDiff->isEmpty()) {
            $output->writeln(
                sprintf(
                    '<info>%s</info>',
                    $this->translate('No change into database schema')
                )
            );
            return Command::SUCCESS;
        }

        $schemaSQL = $platform->getAlterSchemaSQL($schemaDiff);
        if (empty($schemaSQL)) {
            $output->writeln(
                sprintf(
                    '<info>%s</info>',
                    $this->translate('No change into database schema')
                )
            );
            return Command::SUCCESS;
        }

        $output->writeLn('');
        $output->writeLn(
            sprintf(
                '<info>%s</info>',
                $this->translate('Executed Command:')
            )
        );
        $output->writeln('');
        $formatter = new SqlFormatter(new CliHighlighter());
        foreach ($schemaSQL as $query) {
            $query = rtrim($query, ';');
            $output->writeln(
                $formatter->format("$query;"),
                OutputInterface::OUTPUT_RAW
            );
        }

        $answer = $interactive ? $io->ask(
            $this->translate('Are you sure to continue (Yes/No)?'),
            null,
            function ($e) {
                $e = !is_string($e) ? '' : $e;
                $e = strtolower(trim($e));
                $ask = match ($e) {
                    'yes' => true,
                    'no' => false,
                    default => null
                };
                if ($ask === null) {
                    throw new InteractiveArgumentException(
                        $this->translate('Please enter valid answer! (Yes / No)')
                    );
                }
                return $ask;
            }
        ) : true;

        if (!$answer) {
            $output->writeln(
                sprintf(
                    '<comment>%s</comment>',
                    $this->translate('Operation cancelled!')
                )
            );
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln(
            sprintf(
                '<options=bold><comment>%s</comment></>',
                $this->translate('PLEASE DO NOT CANCEL OPERATION!')
            )
        );
        $output->writeln('');

        /**
         * @var PDO $conn
         */
        $conn = $database->getConnection()->getNativeConnection();
        $is_pdo = $conn instanceof PDO;
        if ($is_pdo) {
            $conn->beginTransaction();
            $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
        } else {
            $conn = $database->getConnection();
        }

        $progressBar = !$output->isVerbose() ? $io->createProgressBar() : null;
        $progressBar?->setMaxSteps(count($schemaSQL));
        try {
            foreach ($schemaSQL as $sql) {
                $output->writeln(
                    sprintf(
                        '<info>%s</info> <comment>%s</comment>',
                        $this->translate('Executing:'),
                        $sql
                    ),
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
                $progressBar?->advance();
                if ($is_pdo) {
                    $conn->exec($sql);
                    continue;
                }
                try {
                    $conn->executeStatement($sql);
                } catch (Throwable $e) {
                    $progressBar?->finish();
                    $progressBar?->clear();
                    $output->writeln(
                        sprintf(
                            '<error>%s</error>',
                            $this->translate('Failed to execute command')
                        )
                    );
                    $output->writeln(
                        sprintf(
                            '<fg=red>%s</>',
                            $e->getMessage()
                        )
                    );
                    return Command::FAILURE;
                }
            }
            if ($is_pdo && $conn->inTransaction()) {
                $conn->commit();
            }
            $progressBar?->finish();
            $progressBar?->clear();
        } catch (Throwable $e) {
            if ($is_pdo && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $progressBar?->finish();
            $progressBar?->clear();
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    $this->translate('Failed to execute command')
                )
            );
            $output->writeln(
                sprintf(
                    '<fg=red>%s</>',
                    $e->getMessage()
                )
            );
            return Command::FAILURE;
        }

        $output->writeln(
            sprintf(
                '<info>%s</info>',
                $this->translate('ALL DONE!')
            )
        );
        return Command::SUCCESS;
    }

    private function doDatabaseCheck(
        OutputInterface $output,
        bool $skipChecking
    ) : void {
        $container = $this->getContainer();
        if (!$container?->has(Connection::class)) {
            $this->writeDanger(
                $output,
                $this->translate(
                    'Can not get database object from container'
                )
            );
            return;
        }

        $database = $container->get(Connection::class);
        if (!$database instanceof Connection) {
            $this->writeDanger(
                $output,
                $this->translate(
                    'Database connection is not valid object from container'
                ),
            );
            return;
        }
        //$platform = null;
        $error = null;
        $config = null;
        $ormConfig = null;
        $driverException = null;
        try {
            $config = $database->getDatabaseConfig();
            $ormConfig = $database->getEntityManager()->getConfiguration();
            // $platform = $database->getDatabasePlatform()::class;
            $database->connect();
        } catch (DriverException $e) {
            $error = $e;
            $driverException = $e;
        } catch (Throwable $e) {
            $error = $e;
        }
        if ($error) {
            $this->writeDanger(
                $output,
                $this->translate('Database connection error.')
            );
            $this->writeIndent(
                $output,
                sprintf(
                    '<comment>%s</comment> [<comment>%s</comment>] <fg=red>%s</>',
                    $this->translate('Error:'),
                    $error::class,
                    $error->getMessage()
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
            $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        } else {
            $this->write(
                $output,
                sprintf(
                    '%s [<info>%s</info>]',
                    $this->translate('Database connection succeed'),
                    $database::class
                ),
                self::MODE_SUCCESS
            );
        }

        if (!$error && !$this->entityManager) {
            $orm = clone $ormConfig;
            $cache = new ArrayAdapter();
            $orm->setMetadataCache($cache);
            $orm->setResultCache($cache);
            $orm->setHydrationCache($cache);
            $orm->setQueryCache($cache);
            $this->entityManager = new EntityManager(
                $database->getConnection(),
                $orm,
                $database->getEntityManager()->getEventManager()
            );
        }

        $dbName = $config?->get('dbname');
        $dbName = $dbName?:$config?->get('path');
        $driver = $config?->get('driver');
        $dbUser = $config?->get('user');
        $dbPass = $config?->get('password');
        $dbHost = $driver instanceof Driver\AbstractSQLiteDriver
            ? ($config?->get('path')?:(
            $config?->get('memory') ? ':memory:' : null
            )
            )
            : $config?->get('host');

        $this->write(
            $output,
            sprintf(
                '<info>%s</info> [<comment>%s</comment>]',
                $this->translate('Database name'),
                $dbName ?? '<Empty>'
            ),
            $error ? self::MODE_DANGER : self::MODE_SUCCESS
        );

        $errorX = (bool)$error;
        if ($driverException
            && $driver instanceof Driver\AbstractSQLiteDriver
            && $driverException->getCode() !== 1042
        ) {
            $errorX = false;
        }
        $this->write(
            $output,
            sprintf(
                '<info>%s</info> [<comment>%s</comment>]',
                $this->translate('Database host'),
                $dbHost ?? '<Empty>'
            ),
            $errorX ? self::MODE_DANGER : self::MODE_SUCCESS
        );
        $this->write(
            $output,
            sprintf(
                '<info>%s</info> [<comment>%s</comment>]',
                $this->translate('Database user'),
                $dbUser ?? '<Empty>'
            ),
            $error ? self::MODE_DANGER : self::MODE_SUCCESS
        );
        $this->write(
            $output,
            sprintf(
                '<info>%s</info> [<comment>%s</comment>]',
                $this->translate('Database password'),
                $dbPass ? '<redacted>' : '<Empty>'
            ),
            $error ? self::MODE_DANGER : (
                !$dbPass ? self::MODE_WARNING :self::MODE_SUCCESS
            )
        );

        if ($driver instanceof Driver) {
            $this->writeSuccess(
                $output,
                sprintf(
                    '<info>Driver</info> [<comment>%s</comment>]',
                    $driver::class
                )
            );
        } else {
            $this->writeDanger(
                $output,
                sprintf(
                    // no translate for driver
                    '<info>Driver</info> [<comment>%s</comment>]',
                    $this->translate('Unknown'),
                )
            );
        }

        $this->write(
            $output,
            sprintf(
                '<info>%s</info>',
                $this->translate('Connection Object Info')
            ),
            $error ? self::MODE_WARNING : self::MODE_SUCCESS
        );
        $this->writeIndent(
            $output,
            sprintf(
                '<info>- %s</info> [%s]',
                $this->translate('Connection'),
                $database::class
            )
        );

        $allMetadata = [];
        try {
            $allMetadata = $this
                ->entityManager
                ?->getMetadataFactory()
                ->getAllMetadata() ?? [];
            $metadata = $allMetadata[0] ?? null;
        } catch (Throwable $e) {
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>- Metadata</info> <error>[%s]</error>',
                    $e->getMessage()
                ),
                mode: self::MODE_DANGER
            );
        }
        if (!empty($metadata)) {
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>- EntityManager</info> [%s]',
                    $database->getEntityManager()::class
                )
            );
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>- ClassMetaData</info> [%s]',
                    $metadata ? $metadata::class : 'Unknown'
                )
            );
            try {
                $repository = $metadata
                    ? $database->getRepository($metadata->getName())::class
                    : null;
            } catch (Throwable) {
            }
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>- EntityRepository</info> [%s]',
                    $repository??null
                )
            );
        }

        $this->write(
            $output,
            sprintf(
                '<info>%s</info>',
                $this->translate('ORM Configuration')
            ),
            $error ? self::MODE_DANGER : self::SUCCESS
        );
        if ($ormConfig) {
            $lists = [
                'Query Cache' => $ormConfig->getQueryCache(),
                'Result Cache' => $ormConfig->getResultCache(),
                'Proxy Namespace' => $ormConfig->getProxyNamespace(),
                'Proxy Directory' => $ormConfig->getProxyDir(),
                'Metadata Driver' => $ormConfig->getMetadataDriverImpl(),
                'Repository Factory' => $ormConfig->getRepositoryFactory(),
                'Quote Strategy' => $ormConfig->getQuoteStrategy(),
                'Naming Factory' => $ormConfig->getNamingStrategy(),
                'Schema Manager Factory' => $ormConfig->getSchemaManagerFactory(),
            ];
            foreach ($lists as $key => $obj) {
                $this->writeIndent(
                    $output,
                    sprintf(
                        '<info>- %s</info> [%s]',
                        $key,
                        is_object($obj) ? $obj::class : ( is_string($obj) ? $obj : $this->translate('Not Set'))
                    )
                );
            }
        }
        $this->write(
            $output,
            sprintf(
                '<info>%s (%d)</info>',
                $this->translate('Registered Schema / Entities'),
                count($allMetadata)
            ),
            $error ? self::MODE_DANGER : self::MODE_SUCCESS
        );
        if (!$skipChecking) {
            $this->doCheckData($output, $error, $allMetadata, $database);
        }
    }

    protected function doCheckData(
        OutputInterface $output,
        $error,
        array $allMetadata,
        Connection $database
    ): void {
        $currentSchema = !$error ? (new SchemaTool($this->entityManager))
            ->getSchemaFromMetadata($allMetadata) : null;
        $hasChanged = false;
        $schema = !$error ? $database->createSchemaManager() : null;
        $optimizeArray = [];
        if ($schema && $database->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $tablesMeta = [];
            $inQuestionMark = [];
            foreach ($currentSchema->getTables() as $tableCurrent) {
                if (!$schema->tablesExist($tableCurrent->getName())) {
                    continue;
                }
                $tablesMeta[] = $tableCurrent->getName();
                $inQuestionMark[] = '?';
            }

            if (!empty($tablesMeta)) {
                foreach ($database
                             ->executeQuery(
                                 sprintf(
                                     'SHOW TABLE STATUS WHERE name in (%s)',
                                     implode(', ', $inQuestionMark)
                                 ),
                                 $tablesMeta
                             )->fetchAllAssociative() as $field) {
                    $field = array_change_key_case($field);
                    $field['name'] = strtolower($field['name']);
                    if (((int)$field['data_free']) < 1
                        || ! empty($this->blackListOptimize[$field['name']])
                    ) {
                        continue;
                    }
                    $optimizeArray[$field['name']] = (int)($field['data_free']?:0);
                }
            }
        }
        $containOptimize = false;
        foreach ($allMetadata as $meta) {
            $existTable = $schema?->tablesExist($meta->getTableName());
//            $statusTable = $existTable
//                ? '<fg=green;options=bold>[√]</>'
//                : '<fg=red;options=bold>[X]</>';
//            $this->writeSub(
//                $output,
//                sprintf(
//                    '<info>%s "%s"</info> [<comment>%s</comment>]',
//                    $statusTable,
//                    $meta->getTableName(),
//                    $meta->getName(),
//                ),
//                OutputInterface::VERBOSITY_VERY_VERBOSE
//            );
            $isNeedOptimize = ($optimizeArray[strtolower($meta->getTableName())] ?? 0) > 0;
            if ($isNeedOptimize) {
                $containOptimize = true;
            }
            $changed = [];
            if (!$existTable) {
                $changed[] = 'table';
            } else {
                $currentTable = $currentSchema?->getTable($meta->getTableName());
                $comparator = $schema->createComparator();
                $tableDb = $schema->introspectTable($meta->getTableName());
                $tableDiff = $comparator->compareTables($tableDb, $currentTable);
                if (!empty($tableDiff->changedColumns)
                    || !empty($tableDiff->renamedColumns)
                    || !empty($tableDiff->removedColumns)
                    || !empty($tableDiff->addedColumns)
                ) {
                    $changed[] = 'columns';
                }
                if (!empty($tableDiff->changedIndexes)
                    || !empty($tableDiff->renamedIndexes)
                    || !empty($tableDiff->removedIndexes)
                    || !empty($tableDiff->addedIndexes)
                ) {
                    $changed[] = 'indexes';
                }
                if (!empty($tableDiff->changedForeignKeys)
                    || !empty($tableDiff->removedForeignKeys)
                    || !empty($tableDiff->addedForeignKeys)
                ) {
                    $changed[] = 'foreignKeys';
                }
            }

            $statusTable = empty($changed)
                ? '<fg=green;options=bold>[√]</>'
                : (!$existTable ? '<fg=red;options=bold>[X]</>' : '<fg=yellow;options=bold>[!]</>');
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>%s "%s"</info> [<comment>%s</comment>]%s',
                    $statusTable,
                    $meta->getTableName(),
                    $meta->getName(),
                    $isNeedOptimize
                        ? sprintf(
                            ' <info>[%s]</info>',
                            $this->translate('NEED TO OPTIMIZE')
                        ) : (
                    !$existTable ? ' <fg=red>[NOT EXISTS]</>' : ''
                    ),
                )
            );

            if (!empty($changed)) {
                $hasChanged = true;
                $this->writeIndent(
                    $output,
                    sprintf(
                        '%s%s : <comment>%s</comment>',
                        $this->getSpacing(),
                        $this->translate('Changing'),
                        implode(', ', $changed)
                    ),
                    OutputInterface::VERBOSITY_DEBUG
                );
            }
        }
        if ($hasChanged) {
            $output->writeln('');
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>%s</info>',
                    $this->translate('Contains changed database schema, you can check with command :')
                )
            );
            $output->writeln('');
            $this->writeIndent(
                $output,
                sprintf(
                    '%s<comment>%s %s %s --%s</comment>',
                    $this->getSpacing(),
                    PHP_BINARY,
                    $_SERVER['PHP_SELF'],
                    $this->getName(),
                    $this->schemaCommand
                )
            );
        }
        if ($containOptimize) {
            $output->writeln('');
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>%s</info>',
                    $this->translate('Contains database table that can be optimized, you can execute command :')
                )
            );
            $output->writeln('');
            $this->writeIndent(
                $output,
                sprintf(
                    '<comment>%s %s %s --%s --%s</comment>',
                    PHP_BINARY,
                    $_SERVER['PHP_SELF'],
                    $this->getName(),
                    $this->schemaCommand,
                    $this->optimizeCommand,
                )
            );
            $output->writeln('');
        }
    }
}
