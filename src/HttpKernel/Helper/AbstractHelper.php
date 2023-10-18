<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Cache\Cache;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\HttpKernel\BaseKernel;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Parser\PhpClassParserSerial;
use DateInterval;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionObject;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Finder\Finder;
use Throwable;
use function dirname;
use function is_array;
use function is_dir;
use function is_string;
use function md5;
use function spl_object_hash;
use function sprintf;
use function str_replace;
use function str_starts_with;

abstract class AbstractHelper
{
    /**
     * @var ?CacheItemPoolInterface|false
     */
    private CacheItemPoolInterface|null|false $cache = null;

    protected function __construct(protected BaseKernel $kernel)
    {
    }

    /**
     * @param string $rawIdentity
     * @return string
     */
    protected function generateCacheKey(
        string $rawIdentity
    ): string {
        $rawMd5 = md5($rawIdentity);
        $key = sprintf(
            '%s_%s',
            str_replace('\\', '_', $this::class),
            $rawMd5
        );
        // accepted less or equal 128
        return strlen($key) <= 128
            ? $key
            : sprintf(
                'kernel_service_%s_%s',
                md5($this::class),
                $rawMd5
            );
    }

    /**
     * @param SplFileInfo $splFileInfo
     * @return ?string
     */
    final protected function getClasNameFromFile(SplFileInfo $splFileInfo) : ?string
    {
        if (!$splFileInfo->isFile()) {
            return null;
        }
        $realPath   = $splFileInfo->getRealPath();
        $mtime      = $splFileInfo->getMTime();
        $cacheKey   = $this->generateCacheKey("class:$realPath");
        $cacheItem  = $this->getCacheItem($cacheKey);
        $cacheItems = $cacheItem?->get();
        if (!is_array($cacheItems)
            || !isset($cacheItems['mtime'], $cacheItems['file'], $cacheItems['className'])
            || $cacheItems['mtime'] !== $mtime
            || $cacheItems['file'] !== $realPath
            || (!is_string($cacheItems['className']) && $cacheItems['className'] !== false)
        ) {
            try {
                $parser = PhpClassParserSerial::fromFileInfo($splFileInfo);
                $className = false;
                $ns = null;
                foreach ($parser->getSource() as $stmt) {
                    if ($stmt instanceof Namespace_) {
                        $ns = $stmt->name ? (string)$stmt->name : null;
                        foreach ($stmt->stmts as $st) {
                            if ($st instanceof Class_) {
                                $className = $st->name;
                                break;
                            }
                        }
                        break;
                    }
                    if ($stmt instanceof Class_) {
                        $className = $stmt->name;
                        break;
                    }
                }
                if ($className) {
                    if ($ns) {
                        $className = "$ns\\$className";
                    }
                }
            } catch (Throwable) {
                $className = false;
            }
            $cacheItems = [
                'mtime' => $mtime,
                'className' => $className,
                'file' => $realPath
            ];
            if ($cacheItem) {
                $cacheItem->set($cacheItems);
                $this->saveCacheItem($cacheItem);
            }
            if ($className && !Consolidation::isValidClassName($className)) {
                $className = null;
            }
            return $className?:null;
        }

        if (count($cacheItems) > 3) {
            $cacheItems = [
                'mtime' => $cacheItems['mtime'],
                'className' => $cacheItems['className'],
                'file' => $cacheItems['file']
            ];
            if ($cacheItem) {
                $cacheItem->set($cacheItems);
                $this->saveCacheItem($cacheItem);
            }
        }
        $className = $cacheItems['className']?:null;
        if ($className && !Consolidation::isValidClassName($className)) {
            $className = null;
        }
        return $className?:null;
    }

    final protected function saveClasNameFromFile(
        SplFileInfo $splFileInfo,
        ?string $className
    ) : ?bool {
        if (!$splFileInfo->isFile()) {
            return false;
        }

        if ($className && !Consolidation::isValidClassName($className)) {
            return false;
        }

        $realPath   = $splFileInfo->getRealPath();
        $mtime      = $splFileInfo->getMTime();
        $cacheKey   = $this->generateCacheKey("class:$realPath");
        $cacheItem  = $this->getCacheItem($cacheKey);
        if (!$cacheItem) {
            return false;
        }
        $cacheItem->set([
            'mtime' => $mtime,
            'className' => $className??false,
            'file' => $realPath
        ]);
        return $this->saveCacheItem($cacheItem);
    }

    /**
     * @param SplFileInfo $fileInfo
     * @param SplFileInfo|null $targetInfo
     * @return bool
     */
    final protected function saveCacheItemFileDirectory(
        SplFileInfo $fileInfo,
        ?SplFileInfo $targetInfo = null
    ): bool {
        if (!$fileInfo->isDir() || !($realPath = $fileInfo->getRealPath())) {
            return false;
        }

        $name   = $this->generateCacheKey("file:$realPath");
        $cacheItem ??= $this->getCacheItem($name);
        if (!$cacheItem) {
            return false;
        }
        $cacheItem->set([
            'mtime' => $fileInfo->getMTime(),
            'file' => $realPath,
            'target' => $targetInfo?->getRealPath()?:false
        ]);
        return $this->saveCacheItem($cacheItem);
    }

    /**
     * @param SplFileInfo $fileInfo
     * @return SplFileInfo|false|null
     */
    final protected function getCacheItemFileDirectory(SplFileInfo $fileInfo): SplFileInfo|null|false
    {
        if (!$fileInfo->isDir() || !($realPath = $fileInfo->getRealPath())) {
            return null;
        }

        $name   = $this->generateCacheKey("file:$realPath");
        $cacheItem = $this->getCacheItem($name);
        if (!$cacheItem) {
            return null;
        }
        try {
            $cacheData = $cacheItem->get();
            if ($cacheData === null) {
                return null;
            }
            if (!is_array($cacheData)
                || !isset($cacheData['mtime'], $cacheData['file'], $cacheData['target'])
                || $cacheData['mtime'] !== $fileInfo->getMTime()
                || $cacheData['file'] !== $realPath
                || (!is_string($cacheData['target']) && $cacheData['target'] !== false)
            ) {
                return null;
            }

            $target = $cacheData['target']?:null;
            $target = $target ? new SplFileInfo($target) : null;
            if ($target && ! str_starts_with($target->getRealPath(), $realPath)) {
                $this->deleteCacheItem($name);
                return null;
            } elseif (count($cacheData) > 3) {
                $this->saveCacheItemFileDirectory(
                    $fileInfo,
                    $target
                );
            }
            return $target?:false;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Internal cache for service registration
     *
     * @return ?CacheItemPoolInterface
     */
    final protected function getCache() : ?CacheItemPoolInterface
    {
        if ($this->cache !== null) {
            return $this->cache?:null;
        }
        $this->cache = false;
        if (!Consolidation::isCli()) {
            try {
                $cache = ContainerHelper::use(
                    CacheItemPoolInterface::class,
                    $this->kernel->getHttpKernel()->getContainer()
                );
                $cache = $cache instanceof Cache ? $cache->getAdapter() : $cache;
                $this->cache = $cache instanceof ArrayAdapter || $cache instanceof NullAdapter ? false : $cache;
            } catch (Throwable) {
            }
        }
        return $this->cache?:null;
    }

    /**
     * @param string $name
     * @return ?CacheItemInterface
     */
    final protected function getCacheItem(string $name): ?CacheItemInterface
    {
        try {
            return $this->getCache()?->getItem($name);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    final protected function deleteCacheItem(string $name): bool
    {
        try {
            return $this->getCache()?->deleteItem($name)?:false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param CacheItemInterface|mixed $cacheItem
     * @param int|DateInterval|null $lifetime
     * @return bool
     */
    final protected function saveCacheItem(
        mixed $cacheItem,
        int|DateInterval|null $lifetime = null
    ): bool {
        if (!$cacheItem instanceof CacheItemInterface
            || !($cache = $this->getCache())
        ) {
            return false;
        }
        $cacheItem->expiresAfter($lifetime);
        try {
            return $cache->save($cacheItem);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @var array<string, array<string, int>>
     */
    private static array $registeredKernelServices = [];

    /**
     * @return array<string, string<int>>
     * @noinspection PhpUnused
     */
    public static function getRegisteredKernelServices(): array
    {
        return self::$registeredKernelServices;
    }

    /**
     * @param BaseKernel $kernel
     * @return bool
     */
    final public static function register(BaseKernel $kernel) : bool
    {
        $helper = new static($kernel);
        $kernelId = spl_object_hash($kernel);
        $id = $helper::class;
        self::$registeredKernelServices[$id][$kernelId] ??= 0;
        self::$registeredKernelServices[$id][$kernelId] += $helper->doRegister() ? 1 : 0;
        return true;
    }

    /**
     * @param string $directory
     * @param array|int|string $depth
     * @param ?string $pattern
     * @return ?Finder
     */
    protected function createFinder(
        string $directory,
        array|int|string $depth,
        ?string $pattern = null
    ) : ?Finder {
        if (!is_dir($directory)) {
            return null;
        }
        $finder = Finder::create()
            ->in($directory)
            ->ignoreVCS(true)
            ->ignoreDotFiles(true)
            ->depth($depth);
        if ($pattern) {
            return $finder->name($pattern);
        }
        return $finder;
    }

    /**
     * @param ?object $object
     * @param ?string $factoryNamespace
     * @param string|null $factoryDirectory
     * @return void
     */
    protected function registerAutoloader(
        ?object $object,
        ?string $factoryNamespace = null,
        ?string $factoryDirectory = null
    ): void {
        if (!$object) {
            return;
        }
        $ref = new ReflectionObject($object);
        $namespace = $ref->getNamespaceName();
        if ($factoryNamespace && str_starts_with($factoryNamespace, $namespace)) {
            return;
        }
        $directory = dirname($ref->getFileName());
        if ($directory === $factoryDirectory) {
            return;
        }
        Consolidation::registerAutoloader($namespace, $directory);
    }

    /**
     * @return LoggerInterface|null
     */
    protected function getLogger() : ?LoggerInterface
    {
        return ContainerHelper::use(LoggerInterface::class, $this->getContainer());
    }

    /**
     * @param object $object
     * @return void
     */
    protected function injectDependency(object $object): void
    {
        if ($object instanceof ContainerAllocatorInterface
            && ($container = $this->getContainer())
        ) {
            $object->setContainer($container);
        }
        if ($object instanceof ManagerAllocatorInterface
            && ($manager = $this->getManager())
        ) {
            $object->setManager($manager);
        }
    }

    protected function preProcess()
    {
        // return
    }

    protected function postProcess()
    {
        // return
    }

    protected function getManager() : ?ManagerInterface
    {
        return $this->kernel->getHttpKernel()->getManager();
    }

    protected function getContainer() : ?ContainerInterface
    {
        return $this->kernel->getHttpKernel()->getContainer();
    }

    abstract protected function getMode() : string;

    abstract protected function isProcessable() : bool;

    /**
     * Doing register
     */
    abstract protected function doRegister() : bool;

    abstract protected function loadService(SplFileInfo $splFileInfo);
}
