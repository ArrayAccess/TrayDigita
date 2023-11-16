<?php
/**
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpComposerExtensionStubsInspection
 */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Cache;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Composer\Autoload\ClassLoader;
use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use CouchbaseBucket;
use Memcached;
use PDO;
use Predis\ClientInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Redis;
use RedisArray;
use RedisCluster;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\CouchbaseBucketAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Throwable;
use function call_user_func;
use function class_exists;
use function dirname;
use function file_exists;
use function filetype;
use function interface_exists;
use function is_a;
use function is_array;
use function is_callable;
use function is_int;
use function is_numeric;
use function is_string;
use function min;
use function next;
use function preg_match;
use function reset;
use function trim;

class Cache implements AdapterInterface, ContainerIndicateInterface
{
    use ManagerAllocatorTrait;

    public const DEFAULT_NAMESPACE = 'caches';

    private ?AdapterInterface $adapter = null;
    private int $defaultLifetime = 0;

    public function __construct(protected ContainerInterface $container)
    {
        $manager = ContainerHelper::use(ManagerInterface::class, $this->container);
        if ($manager) {
            $this->setManager($manager);
        }
    }

    protected function configureAdapter()
    {
        $manager = $this->getManager();
        $manager?->dispatch('cache.beforeConfigureAdapter', $this);
        try {
            $config = ContainerHelper::use(Config::class, $this->getContainer());
            $config = $config?->get('cache');
            if (!$config instanceof Config) {
                $adapter = new ArrayAdapter();
                $manager?->dispatch('cache.configureAdapter', $this, $adapter);
                return $adapter;
            }
            $manager = $this->getManager();
            // driver or adapter
            $adapter = $config->get('adapter');
            if (!$adapter || !is_a($adapter, AdapterInterface::class, true)) {
                $adapter = $config->get('driver');
            }
            if (!$adapter || !is_a($adapter, AdapterInterface::class, true)) {
                $adapter = FilesystemAdapter::class;
            }
            if ($adapter instanceof AdapterInterface) {
                $manager?->dispatch('cache.configureAdapter', $this, $adapter);
                return $adapter;
            }

            $namespace = $config->get('namespace');
            $defaultNamespace = !is_string($namespace)
            || trim($namespace) === ''
            || preg_match('~[^-+.A-Za-z0-9]~', trim($namespace))
                ? self::DEFAULT_NAMESPACE
                : trim($namespace);
            // @dispatch(cache.namespace)
            $namespace = $manager?->dispatch('cache.namespace', $defaultNamespace) ?? $defaultNamespace;
            $namespace = !is_string($namespace)
            || trim($namespace) === ''
            || preg_match('~[^-+.A-Za-z0-9]~', trim($namespace))
                ? $defaultNamespace : trim($namespace);

            $lifetime = $config->get('defaultLifetime') ?? $config->get('lifetime');
            $defaultLifetime = !is_int($lifetime) ? 0 : $lifetime;
            // @dispatch(cache.lifetime)
            $lifetime = $manager?->dispatch('cache.lifetime', $lifetime) ?? $lifetime;
            $lifetime = !is_int($lifetime) ? $defaultLifetime : $lifetime;

            $options = $config->get('options');
            $optionDefault = $options instanceof Config ? $options->toArray() : [];
            // @dispatch(cache.options)
            $options = $manager?->dispatch('cache.options', $optionDefault) ?? $optionDefault;
            $options = !is_int($options) ? $optionDefault : $options;

            $marshaller = $config->get('marshaller');
            if (is_string($marshaller)) {
                if (!is_a($marshaller, MarshallerInterface::class, true)) {
                    $marshaller = ContainerHelper::getNull(
                        MarshallerInterface::class,
                        $this->getContainer()
                    );
                    $marshaller ??= new DefaultMarshaller();
                } else {
                    $marshaller = new $marshaller();
                }
            } elseif (!$marshaller instanceof MarshallerInterface) {
                $marshaller = ContainerHelper::getNull(
                    MarshallerInterface::class,
                    $this->getContainer()
                );
                $marshaller ??= new DefaultMarshaller();
            }

            $defaultMarshaller = $marshaller;
            // @dispatch(cache.marshaller)
            $marshaller = $manager?->dispatch('cache.marshaller', $marshaller) ?? $marshaller;
            $marshaller = $marshaller instanceof MarshallerInterface ? $marshaller : $defaultMarshaller;

            $cachePath = $config->get('directory');
            if (!is_string($cachePath)) {
                $cachePath = null;
                try {
                    $ref = new ReflectionClass(ClassLoader::class);
                    $cachePath = dirname($ref->getFileName(), 3) . '/storage/cache';
                } catch (Throwable) {
                }
            }

            $version = $config->get('version');
            $version = !is_string($version) ? null : $version;
            // @dispatch(cache.maxItems)
            $maxItems = (int)($manager?->dispatch('cache.maxItems', 100) ?? 100);
            $maxItems = $maxItems < 10 ? 10 : min($maxItems, 1000);
            // @dispatch(cache.storeSerialize)
            $storeSerialize = (bool)$manager?->dispatch('cache.storeSerialize', true) ?? true;
            // @dispatch(cache.maxLifetime)
            $maxLifetime = (float)$manager?->dispatch('cache.maxLifetime', 0.0) ?? 0.0;

            $this->defaultLifetime = $lifetime;
            // set config
            $config->set('namespace', $namespace);
            $config->set('defaultLifetime', $lifetime);
            $config->set('options', $options);
            $config->set('marshaller', $marshaller);
            $config->set('directory', $cachePath);
            $config->set('version', $version);
            /** @noinspection PhpComposerExtensionStubsInspection */
            $adapterArgs = [
                ArrayAdapter::class => [
                    $lifetime,
                    $storeSerialize,
                    $maxLifetime,
                    $maxItems
                ],
                PdoAdapter::class => [
                    null,
                    $namespace,
                    $lifetime,
                    $options,
                    $marshaller
                ],
                DoctrineDbalAdapter::class => [
                    null,
                    $namespace,
                    $lifetime,
                    $options,
                    $marshaller
                ],
                MemcachedAdapter::class => [
                    Memcached::class,
                    $namespace,
                    $lifetime,
                    $marshaller
                ],
                RedisAdapter::class => [
                    Redis::class,
                    $namespace,
                    $lifetime,
                    $marshaller
                ],
                CouchbaseBucketAdapter::class => [
                    CouchbaseBucket::class,
                    $namespace,
                    $lifetime,
                    $marshaller
                ],
                FilesystemAdapter::class => [
                    $namespace,
                    $lifetime,
                    $cachePath,
                    $marshaller
                ],
                ApcuAdapter::class => [
                    $namespace,
                    $lifetime,
                    $version,
                    $marshaller
                ],
                PhpFilesAdapter::class => [
                    $namespace,
                    $lifetime,
                    $cachePath
                ]
            ];

            if (interface_exists(CacheInterface::class)) {
                $pool = $config->get('pool');
                $pool = $pool instanceof CacheInterface ? $pool : null;
                // @dispatch(cache.pool)
                $pool = $manager?->dispatch('cache.pool', $pool) ?? $pool;
                if ($pool instanceof CacheInterface) {
                    $adapterArgs[Psr16Adapter::class] = [
                        $pool,
                        $namespace,
                        $lifetime
                    ];
                }
            }

            $adapter = isset($adapterArgs[$adapter]) ? $adapter : FilesystemAdapter::class;
            try {
                $ref = new ReflectionClass($adapter);
                if ($ref->hasMethod('isSupported')) {
                    if ($ref->getMethod('isSupported')->isPublic()
                        && $ref->getMethod('isSupported')->isStatic()
                    ) {
                        $adapter = call_user_func([$adapter, 'isSupported'])
                            ? $adapter
                            : FilesystemAdapter::class;
                    } else {
                        $adapter = FilesystemAdapter::class;
                    }
                }
            } catch (Throwable) {
                $adapter = FilesystemAdapter::class;
            }

            switch ($adapter) {
                case DoctrineDbalAdapter::class:
                case PdoAdapter::class:
                    $database = $this->getContainer()?->get(Connection::class);
                    if ($adapter === DoctrineDbalAdapter::class) {
                        $adapterArgs[$adapter][0] = $database->getConnection();
                    } elseif ($database->getNativeConnection() instanceof PDO) {
                        $adapterArgs[$adapter][0] = $database->getNativeConnection();
                    } else {
                        $adapter = FilesystemAdapter::class;
                    }
                    break;
                case RedisAdapter::class:
                    $redis = null;
                    if (class_exists(Redis::class)) {
                        $redis = $this->trygetRedis($config);
                        if ($redis) {
                            $adapterArgs[$adapter][0] = $redis;
                        }
                    }
                    if (!$redis) {
                        $adapter = FilesystemAdapter::class;
                    }
                    break;
                case MemcachedAdapter::class:
                    $memcached = null;
                    if (class_exists(Memcached::class)) {
                        $memcached = $this->tryGetMemcached($config);
                    }
                    if (!$memcached) {
                        $adapter = FilesystemAdapter::class;
                    } else {
                        $adapterArgs[$adapter][0] = $memcached;
                    }
                    break;
                case CouchbaseBucketAdapter::class:
                    $couchbase = null;
                    try {
                        if (class_exists(CouchbaseBucket::class)) {
                            $couchbase = $this->tryGetMemCouchBase($config);
                        }
                    } catch (Throwable) {
                    }
                    if (!$couchbase) {
                        $adapter = FilesystemAdapter::class;
                    } else {
                        $adapterArgs[$adapter][0] = $couchbase;
                    }
                    break;
            }

            // $this->driverArguments = $adapterArgs;
            $adapterArgs = $adapterArgs[$adapter];
            $adapter = new $adapter(...$adapterArgs);
            $manager?->dispatch('cache.configureAdapter', $this, $adapter);
            return $adapter;
        } finally {
            $manager?->dispatch('cache.afterConfigureAdapter', $this);
        }
    }

    public function getDefaultLifetime(): ?int
    {
        return $this->defaultLifetime;
    }

    /**
     * @param Config $config
     * @return ?CouchbaseBucket
     */
    public function tryGetMemCouchBase(
        Config $config
    ) : ?CouchbaseBucket {
        $couchbase = $config->get('couchbase')??$config->get('couchbaseBucket')??null;
        if ($couchbase instanceof CouchbaseBucket) {
            return $couchbase;
        }
        $cluster = $config->get('cluster');
        if (!$cluster instanceof Cluster) {
            $url = $config->get('url')??$config->get('connection');
            if (!is_string($url) || !preg_match('~^couchbases://~i', $url)) {
                return null;
            }
            $options = $config->get('clusterOptions')??$config->get('options');
            if (!$options instanceof ClusterOptions) {
                $options = new ClusterOptions();
            }
            $options = is_array($options) ? $options : null;
            if (!$options) {
                return null;
            }
            $username = $config->get('username');
            $password = $config->get('password');
            if (!is_string($username) || !is_string($password)) {
                return null;
            }
            $options->credentials($username, $password);
            $cluster = new Cluster($url, $options);
        }
        $bucketName = $config->get('bucket')??$config->get('id');
        $bucketName = !is_string($bucketName) ? 'default' : $bucketName;
        return $cluster->bucket($bucketName);
    }

    public function tryGetMemcached(
        Config $config
    ) : ?Memcached {
        $memcached = $config->get('memcached')??$config->get('memcache')??null;
        if (!$memcached instanceof Memcached) {
            if (!class_exists(Memcached::class)) {
                return null;
            }
            try {
                $persistent_id = $config->get('persistent_id') ?? '';
                $persistent_id = !is_string($persistent_id) ? '' : $persistent_id;
                $callback = $config->get('callback');
                $callback = !is_callable($callback) ? null : $callback;
                $memcached = new Memcached($persistent_id, $callback);
            } catch (Throwable) {
                return null;
            }
        }
        $serverList = $memcached->getServerList();
        if (!empty($serverList)) {
            return $memcached;
        }
        $servers = $config->get('servers');
        if (is_array($servers) && !empty($servers)) {
            foreach ($servers as $k => $server) {
                if (!is_array($server)
                    || empty($server)
                ) {
                    unset($servers[$k]);
                    continue;
                }
                if (isset($server['host'])) {
                    $host = $server['host'];
                    $port = $server['port']??false;
                } else {
                    $host = reset($server);
                    $port = next($server);
                }
                if (!is_string($host)) {
                    unset($servers[$k]);
                    continue;
                }
                if ($port !== false && !is_int($port)) {
                    unset($servers[$k]);
                }
            }
        } else {
            $servers = [];
        }
        if (empty($servers)) {
            $host = $config->get('host') ?? '127.0.0.1';
            $host = !is_string($host) ? $host : '127.0.0.1';
            $port = $config->get('port') ?? 11211;
            $weight = $config->get('weight') ?? 0;
            $weight = !is_numeric($weight) ? 0 : (int)$weight;
            $memcached->addServer($host, $port, $weight);
        } else {
            $memcached->addServers($servers);
        }
        return $memcached;
    }

    /**
     * @param Config $config
     * @return Redis|RedisArray|RedisCluster|ClientInterface|null
     */
    public function tryGetRedis(
        Config $config
    ): Redis|RedisArray|RedisCluster|ClientInterface|null {
        $redis = $config->get('redis')??null;
        if (! $redis instanceof Redis &&
            ! $redis instanceof RedisArray &&
            ! $redis instanceof RedisCluster
        ) {
            $dsn = $config->get('dsn')??null;
            $dsn = !is_string($dsn) ? null : $dsn;
            $options = $config->get('options')??[];
            $options = !is_array($options) ? [] : $options;
            if ($dsn && !preg_match('~^rediss?://~', $dsn)) {
                $socket = $config->get('socket') ?? null;
                $dsn = $socket && file_exists($socket)
                && filetype($socket) === 'socket'
                    ? "redis://$socket"
                    : null;
            }
            if (!$dsn) {
                $host = $config->get('host');
                $host = is_string($host) ? $host : '127.0.0.1';
                $port = $config->get('port');
                $port = !is_numeric($port) ? (int) $port : 6379;
                $dsn = "redis://$host:$port";
            }
            $timeout = $config->get('timeout');
            $timeout = is_numeric($timeout) ? (float) $timeout : 0.0;
            $persistent_id = $config->get('persistent_id');
            $persistent_id = !is_string($persistent_id) ? $persistent_id : '';
            $retry_interval = $config->get('retry_interval');
            $retry_interval = !is_numeric($retry_interval) ? (int) $retry_interval : 0;
            $read_timeout = $config->get('read_timeout');
            $read_timeout = !is_numeric($read_timeout) ? (int) $read_timeout : 0;
            $options['timeout'] = !is_numeric($options['timeout']??null)
                ? $timeout
                : (float) $options['timeout'];
            $options['persistent_id'] = !is_string($options['persistent_id']??null)
                ? $persistent_id
                : (string) $options['persistent_id'];
            $options['retry_interval'] = !is_numeric($options['retry_interval']??null)
                ? $retry_interval
                : (int) $options['retry_interval'];
            $options['read_timeout'] = !is_numeric($options['read_timeout']??null)
                ? $read_timeout
                : (int) $options['read_timeout'];
            try {
                return RedisAdapter::createConnection(
                    $dsn,
                    $options
                );
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * @param AdapterInterface $adapter
     */
    public function setAdapter(AdapterInterface $adapter): void
    {
        $this->adapter = $adapter;
    }

    public function getAdapter() : AdapterInterface
    {
        if (!$this->adapter) {
            $this->setAdapter($this->configureAdapter());
        }
        return $this->adapter;
    }

    public function getItem(mixed $key): CacheItem
    {
        return $this->getAdapter()->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->getAdapter()->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->getAdapter()->hasItem($key);
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->getAdapter()->clear($prefix);
    }

    public function deleteItem(string $key): bool
    {
        return $this->getAdapter()->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->getAdapter()->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->getAdapter()->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->getAdapter()->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->getAdapter()->commit();
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }
}
