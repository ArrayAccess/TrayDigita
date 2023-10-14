<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\ResetInterface;
use ArrayAccess\TrayDigita\Cache\Cache;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\TerminableInterface;
use ArrayAccess\TrayDigita\HttpKernel\Traits\HttpKernelInitTrait;
use ArrayAccess\TrayDigita\HttpKernel\Traits\HttpKernelModuleLoaderTrait;
use ArrayAccess\TrayDigita\HttpKernel\Traits\HttpKernelProviderTrait;
use ArrayAccess\TrayDigita\HttpKernel\Traits\HttpKernelSchedulerCommandTrait;
use ArrayAccess\TrayDigita\HttpKernel\Traits\HttpKernelServiceTrait;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Kernel\Interfaces\RebootableInterface;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use ArrayAccess\TrayDigita\Util\Parser\PhpClassParserSerial;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Throwable;
use function filemtime;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function md5;
use function microtime;
use function pathinfo;
use function sha1;
use function sprintf;
use function strtolower;
use const PATHINFO_EXTENSION;

/**
 * @mixin HttpKernelInterface
 */
abstract class BaseKernel implements
    KernelInterface,
    RequestHandlerInterface,
    RebootableInterface
{
    use HttpKernelModuleLoaderTrait;
    use HttpKernelSchedulerCommandTrait;
    use HttpKernelServiceTrait;
    use HttpKernelProviderTrait;
    use HttpKernelInitTrait;

    const DEFAULT_EXPIRED_AFTER = 7200;

    private int $bootStack = 0;

    private bool $booted = false;

    private bool $shutdown = false;

    protected ?float $bootTime = null;

    /**
     * @var int
     */
    protected int $requestStackSize = 0;

    /**
     * @var bool
     */
    protected bool $resetServices = false;

    /**
     * @var array<string, string>
     */
    private array $registeredDirectories = [];

    /**
     * @var array|string[]
     */
    protected array $allowedConfigExtension = [
        'json',
        'yaml',
        'yml',
        'php',
        'env'
    ];

    /**
     * @param HttpKernelInterface $httpKernel
     * @param ?string $baseConfigFileName base config from root path
     *
     * @uses KernelInterface::BASE_CONFIG_FILE_NAME
     */
    public function __construct(
        protected HttpKernelInterface $httpKernel,
        ?string $baseConfigFileName = KernelInterface::BASE_CONFIG_FILE_NAME
    ) {
        if (!is_string($baseConfigFileName)) {
            return;
        }
        $baseConfigFileName = trim($baseConfigFileName);
        $baseConfigFileName = DataNormalizer::normalizeDirectorySeparator($baseConfigFileName);
        $extension = strtolower(pathinfo($baseConfigFileName, PATHINFO_EXTENSION));
        if (in_array($extension, $this->allowedConfigExtension)) {
            $this->baseConfigFile = $baseConfigFileName;
        }
    }

    public function getStartMemory(): int
    {
        return $this->getHttpKernel()->getStartMemory();
    }

    public function getStartTime(): float
    {
        return $this->getHttpKernel()->getStartTime();
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function isShutdown(): bool
    {
        return $this->shutdown;
    }

    /** @noinspection PhpUnused */
    public function getBootTime(): float
    {
        return null !== $this->bootTime ? $this->bootTime : -\INF;
    }

    private CacheItemPoolInterface|null|false $cache = null;

    /**
     * Internal cache for service registration
     *
     * @return ?CacheItemPoolInterface
     */
    private function getInternalCache() : ?CacheItemPoolInterface
    {
        if ($this->cache !== null) {
            return $this->cache?:null;
        }
        $this->cache = false;
        if (!Consolidation::isCli()) {
            try {
                $cache = ContainerHelper::use(
                    CacheItemPoolInterface::class,
                    $this->getHttpKernel()->getContainer()
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
     * @throws InvalidArgumentException
     */
    private function getCacheItem(string $name): ?CacheItemInterface
    {
        return $this->getInternalCache()?->getItem($name);
    }

    /**
     * @param CacheItemInterface $cacheItem
     * @return bool
     */
    private function saveCacheItem(CacheItemInterface $cacheItem): bool
    {
        $cache = $this->getInternalCache();
        if (!$cache) {
            return false;
        }
        $cacheItem->expiresAfter(self::DEFAULT_EXPIRED_AFTER);
        return $cache->save($cacheItem);
    }

    /**
     * @param string $directory
     * @param string $namespace
     * @return ?array{time: int, name: string, data: mixed, item: CacheItemInterface}
     * @throws InvalidArgumentException
     */
    private function getInternalCacheData(
        string $directory,
        string $namespace
    ) : ?array {
        if (!is_dir($directory)) {
            return null;
        }
        $cacheName = sprintf(
            'kernel_cache_%s',
            sha1($namespace)
        );
        $cacheItem = $this->getCacheItem($cacheName);
        if (!$cacheItem) {
            return null;
        }
        $mtime = filemtime($directory);
        $cacheData = $cacheItem->get();
        $cacheData = is_array($cacheData)
        && ($cacheData['name'] ?? null) === $cacheName
        && is_array($cacheData['items'] ?? null)
        && is_string($cacheData['hash'] ?? null)
        && $cacheData['hash'] === md5($mtime . $directory . $namespace)
            ? $cacheData['items']
            : null;
        return [
            'time' => $mtime,
            'name' => $cacheName,
            'data' => $cacheData??null,
            'item' => $cacheItem
        ];
    }

    /**
     * @param string|mixed $cacheName
     * @param int|mixed $mtime
     * @param CacheItemInterface|mixed $cacheItem
     * @param mixed $cacheData
     * @param string $directory
     * @param string $namespace
     * @return void
     */
    private function saveInternalCacheData(
        mixed $cacheName,
        mixed $mtime,
        mixed $cacheItem,
        mixed $cacheData,
        string $directory,
        string $namespace
    ): void {

        $cache = $this->getInternalCache();
        if (!$cache) {
            return;
        }

        if (!is_string($cacheName)
            || !is_int($mtime)
            || ! $cacheItem instanceof CacheItemInterface
        ) {
            return;
        }
        $cacheData = [
            'hash' => md5($mtime . $directory . $namespace),
            'name' => $cacheName,
            'items' => $cacheData
        ];
        $cacheItem
            ->set($cacheData)
            ->expiresAfter(self::DEFAULT_EXPIRED_AFTER);
        unset($cacheData);
        try {
            $cache->save($cacheItem);
        } catch (Throwable) {
        }
    }

    /**
     * @param SplFileInfo $splFileInfo
     * @param string $cacheKey
     * @param $rewrite
     * @param $cacheItem
     * @return array{mtime: int, file:string, classNAme: string}
     * @throws Throwable
     */
    private function getClasNameFromFile(
        SplFileInfo $splFileInfo,
        string $cacheKey,
        &$rewrite,
        &$cacheItem
    ) : array {
        $realPath = $splFileInfo->getRealPath();
        $mtime = $splFileInfo->getMTime();
        $cacheItem = $this->getCacheItem($cacheKey);
        $cacheItems = $cacheItem?->get();
        $rewrite = false;
        if (!is_array($cacheItems)
            || !isset($cacheItems['mtime'], $cacheItems['file'], $cacheItems['className'])
            || $cacheItems['mtime'] !== $mtime
            || $cacheItems['file'] !== $realPath
        ) {
            $parser = PhpClassParserSerial::fromFileInfo($splFileInfo);
            $className = false;
            $ns = null;
            foreach ($parser->getSource() as $stmt) {
                if ($stmt instanceof Namespace_) {
                    $ns = $stmt->name ? (string) $stmt->name : null;
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
            $rewrite = true;
            $cacheItems = [
                'mtime' => $mtime,
                'className' => $className,
                'file' => $realPath
            ];
        }
        return $cacheItems;
    }

    /**
     * @return HttpKernelInterface
     */
    public function getHttpKernel() : HttpKernelInterface
    {
        return $this->httpKernel;
    }

    /**
     * Doing boot
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function boot()
    {
        if ($this->booted === true) {
            if (!$this->requestStackSize
                && true === $this->resetServices
                && $this
                    ->getHttpKernel()
                    ->getContainer()
                    ?->has(ResetInterface::class)
            ) {
                try {
                    $resetter = $this->getHttpKernel()->getContainer()?->get(ResetInterface::class);
                    if ($resetter instanceof ResetInterface) {
                        $resetter->reset();
                    }
                } catch (Throwable) {
                }
            }

            $this->bootTime = microtime(true);
            $this->resetServices = false;
            return;
        }

        ++$this->bootStack;
        $this->shutdown = false;
        $this->bootTime = microtime(true);
        // do init
        !$this->isHasInit() && $this->init();

        $this->preBoot();
        $this->booted = true;
        // @dispatch(kernel.boot)
        $this
            ->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.boot', $this);
        $this->postBoot();
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function shutdown()
    {
        if (!$this->booted || $this->shutdown) {
            return;
        }

        $this->shutdown = true;
        $this->booted = false;
        $this->requestStackSize = 0;
        // @dispatch(kernel.shutdown)
        $this->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.shutdown', $this);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function postBoot()
    {
        // @dispatch(kernel.postBoot)
        $this->getHttpKernel()->getManager()?->dispatch(
            'kernel.postBoot',
            $this
        );
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function preBoot()
    {
        $this->bootTime = microtime(true);
        // @dispatch(kernel.preBoot)
        $this->getHttpKernel()->getManager()?->dispatch(
            'kernel.preBoot',
            $this
        );
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function reboot()
    {
        // @dispatch(kernel.reboot)
        $this->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.reboot', $this);
        $this->shutdown();
        $this->boot();
    }

    public function terminate(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): void {
        // @dispatch(kernel.terminate)
        $this
            ->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.terminate', $this, $request, $response);
        if (($kernel = $this->getHttpKernel()) instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if (!$this->booted) {
            $this->boot();
        }

        $manager = $this->getHttpKernel()->getManager();
        $manager?->dispatch(
            'kernel.beforeHandle',
            $this
        );

        ++$this->requestStackSize;
        $this->resetServices = true;
        try {
            $response = $this->getHttpKernel()->handle($request);
            $manager?->dispatch(
                'kernel.handle',
                $this,
                $response
            );
            return $response;
        } finally {
            --$this->requestStackSize;
            $manager?->dispatch(
                'kernel.afterHandle',
                $this,
                $response??null
            );
        }
    }

    public function run(ServerRequestInterface $request): ResponseInterface
    {
        return $this
            ->getHttpKernel()
            ->dispatchResponse($this->handle($request));
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getHttpKernel()->$name(...$arguments);
    }
}
