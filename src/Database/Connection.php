<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Database\Factory\MetadataFactory;
use ArrayAccess\TrayDigita\Database\Wrapper\DoctrineConnectionExtended;
use ArrayAccess\TrayDigita\Database\Wrapper\DriverWrapper;
use ArrayAccess\TrayDigita\Database\Wrapper\EntityManagerWrapper;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\LegacySchemaManagerFactory;
use Doctrine\ORM\Configuration as OrmConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use SensitiveParameter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use function array_keys;
use function is_dir;
use function is_string;
use function is_subclass_of;
use function method_exists;
use function mkdir;
use function preg_match;
use function preg_replace;
use function realpath;
use function strtolower;
use function trim;

/**
 * @mixin DoctrineConnection
 */
class Connection implements ContainerIndicateInterface, ManagerAllocatorInterface
{
    use ManagerDispatcherTrait,
        ManagerAllocatorTrait;

    /**
     * @var DoctrineConnection|null $connection The connection
     */
    private ?DoctrineConnection $connection = null;

    /**
     * @var OrmConfiguration|Configuration $defaultConfiguration The default configuration
     */
    private OrmConfiguration|Configuration $defaultConfiguration;

    /**
     * @var EventManager|null $eventManager The event manager
     */
    private ?EventManager $eventManager;

    /**
     * @var EntityManagerInterface|null $entityManager The entity manager
     */
    private ?EntityManagerInterface $entityManager = null;

    /**
     * @var bool $configConfigured The config configured
     */
    private bool $configConfigured = false;

    /**
     * @var bool $configurationConfigured The configuration configured
     */
    private bool $configurationConfigured = false;

    /**
     * Connection constructor.
     *
     * @param ContainerInterface $container The container
     * @param Configuration|null $configuration The configuration
     * @param EventManager|null $eventManager The event manager
     * @param Config|null $config The config
     */
    public function __construct(
        protected ContainerInterface $container,
        #[SensitiveParameter]
        ?Configuration $configuration = null,
        EventManager $eventManager = null,
        #[SensitiveParameter]
        protected ?Config $config = null
    ) {
        // register type
        TypeList::registerDefaultTypes();
        $this->config ??= ContainerHelper::use(Config::class, $this->container)??new Config();
        $this->eventManager  = $eventManager??new EventManager();
        $this->defaultConfiguration = $configuration??new OrmConfiguration();
        $manager = ContainerHelper::getNull(ManagerInterface::class, $this->container);
        if ($manager) {
            $this->setManager($manager);
        }
    }

    /**
     * The prefix name identity for event
     *
     * @return string|null
     */
    protected function getPrefixNameEventIdentity(): ?string
    {
        return 'database';
    }

    /**
     * Get the container
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Configure the ORM configuration
     *
     * @param Configuration|null $configuration
     * @return OrmConfiguration
     */
    protected function configureORMConfiguration(?Configuration $configuration) : OrmConfiguration
    {
        try {
            $this->dispatchBefore();
            // @dispatch(database.beforeConfigureORMConfiguration)
            $container = $this->getContainer();
            $factoryCache = ContainerHelper::use(CacheItemPoolInterface::class, $container);
            $orm = $configuration instanceof OrmConfiguration
                ? $configuration
                : new OrmConfiguration();
            $globalConfig = ContainerHelper::service(Config::class, $container);
            $path = $globalConfig->get('path');
            $path = $path instanceof Config ? $path : new Config();

            $database = $globalConfig->get('database');
            $database = $database instanceof Config ? $database : new Config();
            $isDevMode = $database->get('devMode') === true;
            $configuration ??= $orm;

            /**
             * cache, check if null or array
             */
            $noCacheAdapter = $factoryCache instanceof ArrayAdapter
            || $factoryCache instanceof NullAdapter
                ? $factoryCache
                : new ArrayAdapter();
            $resultCache = $configuration->getResultCache() ?? $factoryCache;
            $queryCache = $configuration->getQueryCache() ?? $factoryCache;
            $metadataCache = $configuration->getMetadataCache() ?? $factoryCache;
            $hydrationCache = $configuration->getHydrationCache()??$factoryCache;
            $orm->setResultCache($isDevMode ? $noCacheAdapter : $resultCache ?? $noCacheAdapter);
            $orm->setQueryCache($isDevMode ? $noCacheAdapter : $queryCache ?? $noCacheAdapter);
            $orm->setMetadataCache($isDevMode ? $noCacheAdapter : $metadataCache ?? $noCacheAdapter);
            $orm->setHydrationCache($isDevMode ? $noCacheAdapter : $hydrationCache ?? $noCacheAdapter);
            $orm->setAutoCommit($configuration->getAutoCommit());
            $orm->setMiddlewares($configuration->getMiddlewares());
            $schemaManagerFactory = $configuration
                ->getSchemaManagerFactory()??new LegacySchemaManagerFactory();
            $orm->setSchemaManagerFactory($schemaManagerFactory);

            if (($schema = $configuration->getSchemaAssetsFilter())) {
                $orm->setSchemaAssetsFilter($schema);
            }
            $entityDirectory = null;
            $storage = null;
            $proxyDirectory = null;
            if (method_exists($container, 'getParameter')) {
                $entityDirectory = $container->getParameter('entitiesDirectory');
                $storage = $container->getParameter('storageDirectory');
                $proxyDirectory = $container->getParameter('proxyDirectory');
            }

            $storage = is_string($storage) ? $storage : $path->get('storage');
            $entityDirectory = is_string($entityDirectory) ? $entityDirectory : $path->get('entity');
            $proxyPath = is_string($proxyDirectory) ? $proxyDirectory : $database->get('proxyDirectory');
            $proxyPath = $proxyPath ?: "$storage/database/proxy";
            $proxyPath = $configuration->getProxyDir() ?: $proxyPath;
            if (!is_dir($proxyPath) && ! Consolidation::isCli()) {
                mkdir($proxyPath, 0755, true);
            }

            $metadata = $configuration->getMetadataDriverImpl();
            $proxyNamesSpace = $configuration->getProxyNamespace();
            $proxyNamesSpace = $proxyNamesSpace && trim($proxyNamesSpace, '\\ ') !== ''
                ? $proxyNamesSpace
                : preg_replace('~(.+)\\\[^\\\]+$~', '$1\\Storage\Proxy', __NAMESPACE__);
            if (!$metadata instanceof AttributeDriver) {
                $entityDirectories = [];
                if (is_string($entityDirectory) && is_dir($entityDirectory)) {
                    $entityDirectories[] = realpath($entityDirectory)??
                        DataNormalizer::normalizeUnixDirectorySeparator($entityDirectory, true);
                }

                $metadata = new AttributeDriver(
                    $entityDirectories,
                    true
                );
            }

            $metadata->setFileExtension('.php');
            $metadata->addPaths([$entityDirectory]);
            $orm->setMetadataDriverImpl($metadata);
            $orm->setProxyDir($proxyPath);
            $orm->setProxyNamespace($proxyNamesSpace);
            $orm->setClassMetadataFactoryName(MetadataFactory::class);
            // @dispatch(database.configureORMConfiguration)
            $this->dispatchCurrent($orm);
            return $orm;
        } finally {
            // @dispatch(database.afterConfigureORMConfiguration)
            $this->dispatchAfter($orm??null);
        }
    }

    /**
     * Register entity directory
     *
     * @param string ...$directories
     * @return array|null
     */
    public function registerEntityDirectory(string ...$directories): ?array
    {
        $driver = $this->getDefaultConfiguration()->getMetadataDriverImpl();
        if ($driver instanceof AttributeDriver) {
            $toRegistered = [];
            foreach ($directories as $directory) {
                if (!is_dir($directory)) {
                    continue;
                }
                $directory = realpath($directory);
                if (!$directory) {
                    continue;
                }
                $toRegistered[$directory] = true;
            }
            if (empty($toRegistered)) {
                return null;
            }
            $toRegistered = array_keys($toRegistered);
            $driver->addPaths($toRegistered);
            return $toRegistered;
        }
        return null;
    }

    /**
     * Get the current database configuration
     *
     * @return Config
     */
    public function getDatabaseConfig() : Config
    {
        return $this->configureDatabaseConfig();
    }

    /**
     * Configure the database configuration
     *
     * @return Config
     */
    public function configureDatabaseConfig() : Config
    {
        if ($this->configConfigured) {
            return $this->config;
        }
        if ($this->config->has('database')
            && $this->config->get('database') instanceof Config
        ) {
            $this->config = $this->config->get('database');
        } elseif (!$this->config->has('user')
            && !$this->config->has('dbuser')
            && !$this->config->has('password')
            && !$this->config->has('dbpassword')
            && !$this->config->has('dbname')
            && !$this->config->has('host')
            && !$this->config->has('dbhost')
        ) {
            $this->config = new Config();
        }

        $this->configConfigured = true;
        try {
            // @dispatch(database.beforeConfigureConfig)
            $this->dispatchBefore($this->config);
            $config = $this->config;
            /**
             * @var Config $config
             */
            $defaults = [
                'host' => 'localhost',
                'driver' => Driver\PDO\MySQL\Driver::class,
                'password' => null,
                // 'charset' => 'utf8mb4'
            ];
            if (!$config->has('user') && $config->has('username')) {
                $config->set('user', $config->get('username'));
            }
            if (!$config->has('user') && $config->has('dbuser')) {
                $config->set('user', $config->get('dbuser'));
            }
            if (!$config->has('password') && $config->has('dbpass')) {
                $config->set('password', $config->get('dbpass'));
            }
            if (!$config->has('password') && $config->has('pass')) {
                $config->set('password', $config->get('pass'));
            }

            if (!$config->has('dbname') && $config->has('name')) {
                $config->set('dbname', $config->get('name'));
            }

            $config->addDefaults($defaults);
            $config->set('driver', $this->configureDriver($config));
            if ($config->has('primary') && $config->get('primary') instanceof Config) {
                $config->set('primary', $config->get('primary'));
            } else {
                $config->remove('primary');
            }
            if ($config->has('replica') && $config->get('replica') instanceof Config) {
                $config->set('replica', $config->get('primary'));
            } else {
                $config->remove('primary');
            }
            if ((
                    !$config->get('charset')
                    || !is_string($config->get('charset'))
                    || trim($config->get('charset')) === ''
                ) && $config->get('driver') instanceof AbstractMySQLPlatform
            ) {
                $config->set('charset', 'utf8mb4');
            }
            $this->config = $config;
            // @dispatch(database.configureConfig)
            $this->dispatchCurrent($this->config);
            return $this->config;
        } finally {
            // @dispatch(database.afterConfigureConfig)
            $this->dispatchAfter($this->config);
        }
    }

    /**
     * Get the default configuration
     *
     * @return ?OrmConfiguration
     */
    public function getDefaultConfiguration(): ?OrmConfiguration
    {
        if ($this->configurationConfigured
            && $this->defaultConfiguration instanceof OrmConfiguration
        ) {
            return $this->defaultConfiguration;
        }
        $this->configurationConfigured = true;
        return $this->defaultConfiguration = $this
            ->configureORMConfiguration(
                $this->defaultConfiguration
            );
    }

    /**
     * Configure the driver
     *
     * @param array|Config $params
     * @return Driver
     */
    protected function configureDriver(array|Config $params) : Driver
    {
        $driver = $params['driver']??Driver\PDO\MySQL\Driver::class;
        if (!is_string($driver)) {
            $driver = Driver\PDO\MySQL\Driver::class;
        }

        try {
            if (!is_subclass_of($driver, Driver::class)) {
                $driverName = trim(strtolower($driver));
                $regexes = [
                    // maria-db is a mysql, there was much of unknown people use it
                    '~maria|mysq~' => Driver\PDO\MySQL\Driver::class,
                    '~post?g|pg?sql~' => Driver\PDO\PgSQL\Driver::class,
                    '~sqlite~' => Driver\PDO\SQLite\Driver::class,
                    '~oci|oracle~' => Driver\PDO\OCI\Driver::class,
                    '~ibm|db2~' => Driver\IBMDB2\Driver::class,
                    '~mssql|sqlsrv~' => Driver\PDO\SQLSrv\Driver::class,
                ];
                $currentDriver = Driver\PDO\MySQL\Driver::class;
                foreach ($regexes as $regex => $driverClass) {
                    if (preg_match($regex, $driverName)) {
                        $currentDriver = $driverClass;
                        break;
                    }
                }
                $driver = $currentDriver;
            }
            // @dispatch(database.beforeConfigureDriver)
            $this->dispatchBefore($driver);

            $driverObject =  new $driver;
            // @dispatch(database.configureDriver)
            $this->dispatchCurrent($driver);

            return $driverObject;
        } finally {
            // @dispatch(database.afterConfigureDriver)
            $this->dispatchAfter($driver);
        }
    }

    /**
     * Get the doctrine connection
     *
     * @return DoctrineConnection
     */
    public function getConnection() : DoctrineConnection
    {
        return $this->setUpConnection();
    }

    /**
     * Set up the connection
     *
     * @return DoctrineConnection
     */
    protected function setUpConnection() : DoctrineConnection
    {
        if ($this->connection) {
            return $this->connection;
        }
        try {
            // @dispatch(database.beforeSetUpConnection)
            $this->dispatchBefore();
            $config = $this->getDatabaseConfig()->toArray();
            $driver = $config['driver'];
            unset($config['driver']);
            $configuration = $this->getDefaultConfiguration();
            foreach ($configuration->getMiddlewares() as $middleware) {
                $driver = $middleware->wrap($driver);
            }

            $this->connection = new DoctrineConnectionExtended(
                $config,
                new DriverWrapper($this, $driver),
                $configuration,
                $this->eventManager
            );

            // @dispatch(database.setUpConnection)
            $this->dispatchCurrent();

            $container = $this->getContainer();

            // !!IMPORTANT FOR SCHEMA
            // handle default events
            ContainerHelper::use(DatabaseEventsCollector::class, $container)
                ?->registerDefaultEvent();

            $this->entityManager ??= new EntityManagerWrapper($this);

            return $this->connection;
        } finally {
            // @dispatch(database.afterSetUpConnection)
            $this->dispatchAfter();
        }
    }

    /**
     * Get the entity manager
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager() : EntityManagerInterface
    {
        if (!$this->entityManager) {
            $this->setUpConnection();
            return $this->entityManager ??= new EntityManagerWrapper($this);
        }
        return $this->entityManager;
    }

    /**
     * Wrap the entity
     *
     * @param EntityManagerInterface|ObjectManager $entityManager
     * @return EntityManagerWrapper
     */
    public function wrapEntity(EntityManagerInterface|ObjectManager $entityManager): EntityManagerWrapper
    {
        $connection = $this;
        if ($entityManager instanceof EntityManagerWrapper) {
            if ($this->entityManager === $entityManager) {
                return $this->entityManager;
            }
            $connection = $entityManager->getConnection();
            $entityManager = $entityManager->getWrappedEntity();
        }
        // check is same
        if ($connection === $this) {
            return $this->entityManager ??= new EntityManagerWrapper(
                $connection,
                $entityManager
            );
        }
        return new EntityManagerWrapper(
            $connection,
            $entityManager
        );
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return ObjectRepository&Selectable<T>
     */
    public function getRepository(string $className): ObjectRepository&Selectable
    {
        return $this->getEntityManager()->getRepository($className);
    }

    /**
     * @template T of object
     * @template-implements Selectable<int,T>
     * @template-implements ObjectRepository<T>
     * Finds entities by a set of criteria.
     *
     * @param class-string $entityClass
     * @param int|null $limit
     * @param int|null $offset
     * @psalm-param array<string, mixed> $criteria
     * @psalm-param array<string, string>|null $orderBy
     *
     * @return array<T> The objects.
     * @psalm-return list<T>
     */
    public function findBy(
        string $entityClass,
        array $criteria,
        ?array $orderBy = null,
        int $limit = null,
        int $offset = null
    ): array {
        return $this
            ->getRepository($entityClass)
            ->findBy(
                $criteria,
                $orderBy,
                $limit,
                $offset
            );
    }

    /**
     * @template T of object
     * @template-implements Selectable<int,T>
     * @template-implements ObjectRepository<T>
     * Finds entity by a set of criteria.
     *
     * @param class-string $entityClass
     * @psalm-param array<string, mixed> $criteria
     * @psalm-param array<string, string>|null $orderBy
     *
     * @return ?T The objects.
     * @psalm-return ?T
     */
    public function findOneBy(
        string $entityClass,
        array $criteria,
        ?array $orderBy = null,
    ) {
        return $this
            ->getRepository($entityClass)
            ->findOneBy(
                $criteria,
                $orderBy
            );
    }

    /**
     * @template T of object
     * @template-implements Selectable<int,T>
     * @template-implements ObjectRepository<T>
     * Finds an entity by its primary key / identifier.
     *
     * @param class-string<T> $entityClass
     * @param mixed    $id          The identifier.
     * @param int|null $lockMode    One of the \Doctrine\DBAL\LockMode::* constants
     *                              or NULL if no specific lock mode should be used
     *                              during the search.
     * @param int|null $lockVersion The lock version.
     * @psalm-param \Doctrine\DBAL\LockMode::*|null $lockMode
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     * @psalm-return ?T
     */
    public function find(
        string $entityClass,
        mixed $id,
        ?int $lockMode = null,
        ?int $lockVersion = null
    ) {
        return $this
            ->getRepository($entityClass)
            ->find($id, $lockMode, $lockVersion);
    }

    /**
     * Find all entities in the repository.
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @return array<T>
     */
    public function findAll(string $entityClass): array
    {
        return $this
            ->getRepository($entityClass)
            ->findAll();
    }

    /**
     * Magic method to call Doctrine\DBAL\Connection methods
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->getConnection()->$name(...$arguments);
    }
}
