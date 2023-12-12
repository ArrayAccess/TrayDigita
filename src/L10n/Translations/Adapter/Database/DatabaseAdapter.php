<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Adapter\Database;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\PluralForm;
use ArrayAccess\TrayDigita\L10n\Translations\AbstractAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Entries;
use ArrayAccess\TrayDigita\L10n\Translations\Entry;
use ArrayAccess\TrayDigita\L10n\Translations\Exceptions\UnsupportedLanguageException;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Throwable;
use function sprintf;
use function strtolower;
use function trim;

class DatabaseAdapter extends AbstractAdapter
{
    /**
     * @var DoctrineConnection|Connection $connection The database connection
     */
    protected DoctrineConnection|Connection $connection;

    /**
     * @var string $tableName The table name
     */
    protected string $tableName = 'translations';

    /**
     * @var array<array<Entries|false>> $records The records
     */
    private array $records = [];

    /**
     * @var bool $initSchema The init schema
     */
    private bool $initSchema = false;

    /**
     * @var PluralForm $pluralForm The plural form
     */
    private PluralForm $pluralForm;

    /**
     * DatabaseAdapter constructor.
     *
     * @param TranslatorInterface $translator
     * @param Connection|DoctrineConnection $connection
     */
    public function __construct(
        TranslatorInterface $translator,
        Connection|DoctrineConnection $connection
    ) {
        parent::__construct($translator);
        $this->connection = $connection;
        $this->pluralForm = new PluralForm(
            2,
            'n != 1'
        );
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Database';
    }

    /**
     * @return string The table name
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @param string $language
     * @param string $domain
     *
     * @return ?Entries
     * @throws Throwable
     */
    public function databaseRecords(
        string $language,
        string $domain
    ) : ?Entries {
        $language = strtolower($language);
        $domain = trim(strtolower($domain));
        if (isset($this->records[$language][$domain])) {
            return $this->records[$language][$domain]?:new Entries();
        }
        $this->records[$language][$domain] = false;
        $entries = new Entries();
        foreach ($this->findAllFrom($language, $domain) as $translation) {
            $entry = Entry::create(
                $translation['original'],
                null,
                [$translation['translation'], $translation['plural_translation']],
                null,
                $this->pluralForm
            );
            $entries->add($entry);
        }
        if (count($entries) > 0) {
            $this->records[$language][$domain] = $entries;
        }
        return $this->records[$language][$domain]?:null;
    }

    /**
     * @param string $language
     * @param string $original
     * @param string $domain
     * @param string|null $context
     *
     * @return ?EntryInterface
     * @throws Throwable
     */
    public function find(
        string $language,
        string $original,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
        ?string $context = null
    ) : ?EntryInterface {
        return $this
            ->databaseRecords($language, $domain)
            ?->entry(self::generateId($context, $original));
    }

    /**
     * @param string $language
     * @param string $domain
     *
     * @return Entries
     * @throws Throwable
     */
    public function all(string $language, string $domain = TranslatorInterface::DEFAULT_DOMAIN) : Entries
    {
        return $this->databaseRecords($language, $domain)??new Entries();
    }

    /**
     * @param string $language
     * @param string $domain
     *
     * @return array{array{language:string,domain:string,original:string,translation:string|null,plural:string|null}}
     * @throws Throwable
     */
    public function findAllFrom(string $language, string $domain = 'default') : array
    {
        $this->configureSchema();
        $locale = Locale::normalizeLocale($language);
        if (!$locale) {
            return [];
        }

        $exp = $this->connection->createExpressionBuilder();
        return $this
            ->connection
            ->createQueryBuilder()
            ->select('*')
            ->where($exp->eq('language', ':language'), $exp->eq('domain', ':domain'))
            ->from($this->getTableName())
            ->setParameters(['language' => $locale, 'domain' => $domain])
            ->fetchAllAssociative();
    }

    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \Doctrine\DBAL\Exception
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->getTableName());
        $table->addColumn(
            'id',
            Types::BIGINT,
            [
                'length' => 20,
                'unsigned' => true,
                'autoIncrement' => true
            ]
        )->setAutoincrement(true);

        $table->addColumn(
            'language',
            Types::STRING,
            [
                'length' => 10
            ]
        );
        $table->addColumn(
            'domain',
            Types::STRING,
            [
                'default' => 'default',
                'length' => 128
            ]
        );
        $table->addColumn(
            'original',
            Types::STRING,
            [
                'length' => 1024
            ]
        );
        $table->addColumn(
            'translation',
            Types::STRING,
            [
                'length' => 1024,
                'notNull' => false,
            ]
        );
        $table->addColumn(
            'plural_translation',
            Types::STRING,
            [
                'length' => 1024,
                'notNull' => false,
                'default' => null
            ]
        );

        $table->addColumn(
            'context',
            Types::STRING,
            [
                'length' => 2048,
                'notNull' => false,
                'default' => null
            ]
        );

        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
            $table->addOption('charset', 'utf8mb4');
            $table->addOption('collation', 'utf8mb4_unicode_ci');
        }

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(
            [
                'language',
                'domain',
                'original'
            ],
            'unique_language_domain_original'
        );
        $table->addIndex([
            'language',
        ], 'index_language');
        $table->addIndex([
            'domain',
        ], 'index_domain');
        $table->setComment('Translations table');
    }

    /**
     * Configure schema
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \Doctrine\DBAL\Exception
     * @noinspection PhpFullyQualifiedNameUsageInspection
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
            $dbTable = $schemaManager->introspectTable($this->tableName);
            $table = $schema->getTable($this->tableName);
            $change = false;
            $typeRegistry = Type::getTypeRegistry();

            foreach ($table->getColumns() as $column) {
                if (!$dbTable->hasColumn($column->getName())) {
                    $change = true;
                    break;
                }
                $dbColumn = $dbTable->getColumn($column->getName());
                if ($dbColumn->getLength() !== $column->getLength()
                    || $typeRegistry->lookupName(
                        $dbColumn->getType()
                    ) !== $typeRegistry->lookupName($column->getType())
                    || $column->getNotnull() !== $dbColumn->getNotnull()
                    || $column->getDefault() !== $dbColumn->getDefault()
                ) {
                    $change = true;
                    break;
                }
            }
            if (!$change) {
                return;
            }

            $comparator = $schemaManager->createComparator();
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

    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \Doctrine\DBAL\Exception
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function save(string $language, string $domain, Entry $entry): int
    {
        $this->configureSchema();
        $locale = Locale::normalizeLocale($language);
        if (!$locale) {
            throw new UnsupportedLanguageException(
                sprintf('Language "%s" is invalid or not supported', $language)
            );
        }

        $original = $entry->getOriginal();
        $translation = $entry->getTranslationIndex();
        $plural = $entry->getPlural();
        $exp = $this
            ->connection
            ->createExpressionBuilder();
        $exists = $this
            ->connection
            ->createQueryBuilder()
            ->select('1')
            ->from($this->getTableName())
            ->where(
                $exp->eq('language', ':language'),
                $exp->eq('original', ':original'),
                $exp->eq('domain', ':domain'),
            )->setParameters([
                'language' => $locale,
                'original' => $original,
                'domain' => $domain,
            ])->fetchOne();
        if ($exists) {
            return $this
                ->connection
                ->createQueryBuilder()
                ->update($this->getTableName())
                ->where(
                    $exp->eq('language', ':language'),
                    $exp->eq('original', ':original'),
                    $exp->eq('domain', ':domain'),
                )
                ->set('translation', ':translation')
                ->set('plural_translation', ':plural_translation')
                ->setParameters([
                    'language' => $locale,
                    'original' => $original,
                    'domain' => $domain,
                    'translation' => $translation,
                    'plural_translation' => $plural,
                ])->executeStatement();
        } else {
            return
                $this
                    ->connection
                    ->createQueryBuilder()
                    ->insert($this->getTableName())
                    ->values([
                        'language' => ':language',
                        'original' => ':original',
                        'translation' => ':translation',
                        'domain' => ':domain',
                        'plural_translation' => ':plural_translation',
                    ])->setParameters([
                        'language' => $locale,
                        'original' => $original,
                        'translation' => $translation,
                        'domain' => $domain,
                        'plural_translation' => $plural,
                    ])->executeStatement();
        }
    }

    /**
     * @var array<string, array<string, array<string, Entry>>
     */
    protected array $deferredSave = [];

    /**
     * Save deferred
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \Doctrine\DBAL\Exception
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function saveDeferred(string $language, string $domain, Entry $entry): void
    {
        $this->configureSchema();
        $locale = Locale::normalizeLocale($language);
        if (!$locale) {
            throw new UnsupportedLanguageException(
                sprintf('Language "%s" is invalid or not supported', $language)
            );
        }

        $this->deferredSave[$locale][$domain][$entry->getOriginal()] = $entry;
    }

    /**
     * Commit deferred
     *
     * @return void
     */
    public function commit(): void
    {
        foreach ($this->deferredSave as $locale => $entries) {
            foreach ($entries as $domain => $entry) {
                foreach ($entry as $translation) {
                    try {
                        $this->save($locale, (string) $domain, $translation);
                    } catch (Throwable) {
                    }
                }
            }
        }
    }

    /**
     * Clear the records
     *
     * @return void
     */
    public function clear(): void
    {
        $this->records = [];
    }

    /**
     * Magic method __destruct, clear the records
     */
    public function __destruct()
    {
        $this->clear();
    }
}
