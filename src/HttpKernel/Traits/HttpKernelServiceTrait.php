<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Traits;

use ArrayAccess\TrayDigita\Database\DatabaseEventsCollector;
use ArrayAccess\TrayDigita\Database\Interfaces\DatabaseEventInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Middleware\AbstractMiddleware;
use ArrayAccess\TrayDigita\Routing\Interfaces\ControllerInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;
use function array_filter;
use function dirname;
use function is_array;
use function is_callable;
use function is_dir;
use function is_string;
use function ksort;
use function sprintf;
use const ARRAY_FILTER_USE_BOTH;

trait HttpKernelServiceTrait
{
    protected function registerServices() : void
    {
        if (!empty($this->serviceRegistered)) {
            return;
        }

        $this->serviceRegistered = true;
        // make sure scheduler is registered
        if (!$this->schedulerRegistered) {
            $this->registerSchedulers();
        }
        /*! FILE SCAN */
        $toLoad = [
            $this->middlewareNamespace,
            $this->controllerNameSpace,
            $this->databaseEventNameSpace,
        ];
        /*! CLASS NAME SUBCLASS */
        $subClass = [
            MiddlewareInterface::class,
            ControllerInterface::class,
            DatabaseEventInterface::class
        ];
        $kernel = $this->getHttpKernel();
        $manager = ContainerHelper::use(ManagerInterface::class, $kernel->getContainer());
        /*! HANDLERS */
        /**
         * @var array<callable> $handlers
         */
        $handlers = [
            [$this, 'internalLoadMiddleware'],
            [$this, 'internalLoadController'],
            [$this, 'internalLoadDatabaseEvent'],
        ];
        // do not register controller & events if config error
        if ($this->getConfigError()) {
            unset(
                $toLoad[$this->controllerNameSpace],
                $toLoad[$this->databaseEventNameSpace],
                $subClass[ControllerInterface::class],
                $subClass[DatabaseEventInterface::class],
                $handlers[1],
                $handlers[2],
            );
        }
        foreach ($toLoad as $key => $namespace) {
            unset($toLoad[$key]);
            if (!is_string($namespace)
                || !isset($this->registeredDirectories[$namespace])
                || !is_dir($this->registeredDirectories[$namespace])
            ) {
                unset($subClass[$key], $handlers[$key]);
                continue;
            }
            $toLoad[$namespace] = $this->registeredDirectories[$namespace];
            $subClass[$namespace] = $subClass[$key];
            $handlers[$namespace] = $handlers[$key];
            unset($subClass[$key], $handlers[$key]);
        }

        /*
        $cache = $this->getInternalCache();
        $cacheItems = [];
        foreach ($toLoad as $namespace => $directory) {
            if (!$cache
                || !is_string($namespace)
                || !isset($handlers[$namespace])
                || !isset($subClass[$namespace])
                || !is_dir($directory)
            ) {
                continue;
            }
            $cacheData = $this->getInternalCacheData(
                $directory,
                $namespace
            );
            if ($cacheData === null) {
                continue;
            }

            $cacheItems[$namespace] = $cacheData;
            if (!is_array($cacheData['data'])) {
                continue;
            }
            $cacheData = $cacheData['data'];
            foreach ($cacheData as $key => $item) {
                if (!is_string($key)
                    || !is_string($item)
                    || !is_subclass_of($item, $subClass[$namespace])
                ) {
                    continue 2;
                }
            }

            if ([] === $cacheData) {
                continue;
            }

            try {
                if (is_callable($handlers[$namespace])) {
                    $handlers[$namespace]($cacheData, $kernel, $manager);
                    unset($handlers[$namespace]);
                }
            } catch (Throwable $e) {
            }
            unset(
                $cacheItems[$namespace],
                $toLoad[$namespace]
            );
        }

        if ([] == $toLoad) {
            return;
        }*/
        foreach ($toLoad as $namespace => $directory) {
            if (!is_dir($directory)) {
                unset($toLoad[$namespace]);
                continue;
            }

            $maxDepth = 20;
            $times = '';
            $files = Finder::create()
                ->in($directory)
                ->ignoreVCS(true)
                ->ignoreDotFiles(true)
                // depth <= 10
                ->depth("<= $maxDepth")
                ->name('/^[_A-za-z]([_a-zA-Z0-9]+)?\.php$/')
                ->files();
            foreach ($files as $splFileInfo) {
                $times .= $splFileInfo->getMTime() . $splFileInfo->getBasename();
            }

            $hash = md5($directory . $times);
            $cacheGlobalKey = 'kernel_services_'. md5((string) $namespace);
            $cacheItemGlobal = $this->getCacheItem($cacheGlobalKey);
            $cacheData = $cacheItemGlobal?->get();
            $toLoad[$namespace] = [];
            if (is_array($cacheData)
                && is_array($cacheData['list']??null)
                && is_array($cacheData['directories']??null)
                && is_array($cacheData['includes']??null)
                && is_string($cacheData['hash']??null)
                && ($cacheData['key']??null) === $cacheGlobalKey
                && ($cacheData['namespace']??null) === $namespace
                && $hash === $cacheData['hash']
            ) {
                $toLoad[$namespace] = $cacheData['list'];
                $cacheData['directories'] = array_filter(
                    $cacheData['directories'],
                    static fn($a, $b) => is_string($a) && is_string($b),
                    ARRAY_FILTER_USE_BOTH
                );
                foreach ($cacheData['includes'] as $file) {
                    if (!is_string($file)) {
                        continue;
                    }
                    (function ($realPath) {
                        require_once $realPath;
                    })($file);
                }
                if ($cacheData['directories'] !== []) {
                    $this->registeredDirectories = $cacheData['directories']
                        + $this->registeredDirectories;
                }
            } else {
                $directoryNamespace = [];
                $includes = [];
                foreach ($files as $splFileInfo) {
                    $realPath = $splFileInfo->getRealPath();
                    $cacheKey = sprintf('kernel_service_%s', md5($realPath));
                    try {
                        $cacheItems = $this->getClasNameFromFile(
                            $splFileInfo,
                            $cacheKey,
                            $rewrite,
                            $cacheItem
                        );
                        $className = $cacheItems['className'];
                        if (!$className) {
                            if ($rewrite && $cacheItem) {
                                $cacheItem->set($cacheItems);
                                $this->saveCacheItem($cacheItem);
                            }
                            continue;
                        }
                        $succeed = false;
                        try {
                            $reflection = new ReflectionClass($className);
                            $succeed = true;
                        } catch (Throwable) {
                            (function ($realPath) {
                                require_once $realPath;
                            })($realPath);
                        }

                        $reflection ??= new ReflectionClass($className);
                        $className = $reflection->getName();
                        if ($rewrite && $cacheItem) {
                            $cacheItems['className'] = $className;
                            $cacheItem->set($cacheItems);
                            $this->saveCacheItem($cacheItem);
                        }
                        if (!$reflection->isInstantiable()
                            || $reflection->getFileName() !== $realPath
                        ) {
                            continue;
                        }
                        $toLoad[$namespace][$realPath] = $className;
                        $includes[] = $realPath;
                        if ($succeed) {
                            continue;
                        }
                        $ns = $reflection->getNamespaceName();
                        if ($ns && !isset($this->registeredDirectories["$ns\\"])) {
                            $directoryNamespace["$ns\\"] = dirname($reflection->getFileName());
                        }
                    } catch (Throwable) {
                        continue;
                    }
                }

                // save cache
                if ($cacheItemGlobal) {
                    $cacheData = [
                        'hash' => $hash,
                        'key' => $cacheGlobalKey,
                        'namespace' => $namespace,
                        'list' => $toLoad[$namespace],
                        'directories' => $directoryNamespace,
                        'includes' => $includes,
                    ];
                    $cacheItemGlobal->set($cacheData);
                    $cacheItemGlobal->expiresAfter(self::DEFAULT_EXPIRED_AFTER);
                    $this->saveCacheItem($cacheItemGlobal);
                }

                if ([] !== $toLoad[$namespace]) {
                    if ([] !== $directoryNamespace) {
                        $this->registeredDirectories = $directoryNamespace
                            + $this->registeredDirectories;
                    }
                    continue;
                }
                unset($toLoad[$namespace]);
            }

            /*
            if (!isset($cacheItems[$namespace])) {
                continue;
            }
            $this->saveInternalCacheData(
                $cacheItems[$namespace]['name'],
                $cacheItems[$namespace]['time'],
                $cacheItems[$namespace]['item'],
                []
            );
            unset($cacheItems[$namespace]);
            */
        }

        if ([] !== $toLoad) {
            foreach ($toLoad as $namespace => $files) {
                try {
                    if (is_callable($handlers[$namespace])) {
                        $handlers[$namespace]($files, $kernel, $manager);
                        unset($handlers[$namespace]);
                    }
                } catch (Throwable) {
                }
                /*
                if (isset($cacheItems[$namespace])) {
                    $this->saveInternalCacheData(
                        $cacheItems[$namespace]['name'],
                        $cacheItems[$namespace]['time'],
                        $cacheItems[$namespace]['item'],
                        $files
                    );
                    unset($cacheItems[$namespace]);
                }*/
            }
        }
    }

    /**
     * @param array $loadedFiles
     * @param HttpKernelInterface $kernel
     * @param ManagerInterface|null $manager
     * @return void
     * @throws Throwable
     */
    private function internalLoadDatabaseEvent(
        array $loadedFiles,
        HttpKernelInterface $kernel,
        ?ManagerInterface $manager
    ): void {
        $eventCollector = ContainerHelper::getNull(
            DatabaseEventsCollector::class,
            $kernel->getContainer()
        );
        if (!$eventCollector instanceof DatabaseEventsCollector) {
            return;
        }
        try {
            $manager?->dispatch(
                'kernel.beforeRegisterDatabaseEvents',
                $eventCollector,
                $kernel,
                $this
            );
            foreach ($loadedFiles as $className) {
                $event = $eventCollector->createFromClassName($className);
                if (!$event) {
                    continue;
                }
                $manager?->dispatch(
                    'kernel.beforeRegisterDatabaseEvent',
                    $event,
                    $eventCollector,
                    $kernel,
                    $this
                );
                try {
                    $eventCollector->add($event);
                    $manager?->dispatch(
                        'kernel.registerDatabaseEvent',
                        $event,
                        $eventCollector,
                        $kernel,
                        $this
                    );
                } finally {
                    $manager?->dispatch(
                        'kernel.afterRegisterDatabaseEvent',
                        $event,
                        $eventCollector,
                        $kernel,
                        $this
                    );
                }
            }
            $manager?->dispatch(
                'kernel.registerDatabaseEvents',
                $eventCollector,
                $kernel,
                $this
            );
        } finally {
            $manager?->dispatch(
                'kernel.afterRegisterDatabaseEvents',
                $eventCollector,
                $kernel,
                $this
            );
        }
    }

    private function internalLoadController(
        array $loadedFiles,
        HttpKernelInterface $kernel,
        ?ManagerInterface $manager
    ): void {
        $container = $kernel->getContainer();
        $router = $kernel->getRouter();
        try {
            $manager?->dispatch(
                'kernel.beforeRegisterControllers',
                $kernel,
                $this,
                $router
            );
            foreach ($loadedFiles as $className) {
                try {
                    $manager?->dispatch(
                        'kernel.beforeRegisterController',
                        $className,
                        $kernel,
                        $this,
                        $router
                    );
                    $route = $router->addRouteController($className);
                    $manager?->dispatch(
                        'kernel.registerController',
                        $className,
                        $kernel,
                        $router,
                        $route
                    );
                } catch (Throwable $e) {
                    $logger ??= ContainerHelper::getNull(LoggerInterface::class, $container) ?: false;
                    if ($logger instanceof LoggerInterface) {
                        $logger->debug($e, ['context' => 'middleware']);
                    }
                } finally {
                    $manager->dispatch(
                        'kernel.afterRegisterController',
                        $className,
                        $kernel,
                        $this,
                        $router,
                        $route ?? null
                    );
                }
            }
            $manager?->dispatch(
                'kernel.registerControllers',
                $kernel,
                $this,
                $router
            );
        } finally {
            $manager?->dispatch(
                'kernel.afterRegisterControllers',
                $kernel,
                $this,
                $router
            );
        }
    }

    /**
     * Load middlewares
     *
     * @param array $loadedFiles
     * @param HttpKernelInterface $kernel
     * @param ManagerInterface|null $manager
     * @return void
     */
    private function internalLoadMiddleware(
        array $loadedFiles,
        HttpKernelInterface $kernel,
        ?ManagerInterface $manager
    ): void {

        try {
            $manager?->dispatch(
                'kernel.beforeResolveMiddlewares',
                $kernel,
                $this
            );
            $middlewares = [];
            $container = $kernel->getContainer() ?? Decorator::container();
            foreach ($loadedFiles as $className) {
                try {
                    $manager?->dispatch(
                        'kernel.beforeResolveMiddleware',
                        $className,
                        $kernel,
                        $this
                    );
                    $middleware = ContainerHelper::resolveCallable(
                        $className,
                        $container
                    );
                    $priority = AbstractMiddleware::DEFAULT_PRIORITY;
                    if ($middleware instanceof AbstractMiddleware) {
                        $priority = $middleware->getPriority();
                    }
                    $middlewares[$priority][] = $middleware;
                    $manager?->dispatch(
                        'kernel.resolveMiddleware',
                        $className,
                        $kernel,
                        $this,
                        $middleware
                    );
                } catch (Throwable $e) {
                    $logger ??= ContainerHelper::getNull(LoggerInterface::class, $container) ?: false;
                    if ($logger instanceof LoggerInterface) {
                        $logger->debug($e, ['context' => 'middleware']);
                    }
                } finally {
                    $manager?->dispatch(
                        'kernel.afterResolveMiddleware',
                        $className,
                        $kernel,
                        $this,
                        $middleware ?? null
                    );
                }
            }
            ksort($middlewares);
            $manager?->dispatch(
                'kernel.resolveMiddlewares',
                $kernel,
                $this
            );
        } finally {
            $manager?->dispatch(
                'kernel.afterResolveMiddlewares',
                $kernel,
                $this
            );
        }

        try {
            $manager?->dispatch(
                'kernel.beforeRegisterMiddlewares',
                $kernel,
                $this
            );

            foreach ($middlewares as $middlewareList) {
                foreach ($middlewareList as $middleware) {
                    try {
                        $manager?->dispatch(
                            'kernel.beforeRegisterMiddleware',
                            $middleware,
                            $kernel,
                            $this
                        );
                        $kernel->addMiddleware($middleware);
                        $manager?->dispatch(
                            'kernel.registerMiddleware',
                            $middleware,
                            $kernel,
                            $this
                        );
                    } finally {
                        $manager?->dispatch(
                            'kernel.afterRegisterMiddleware',
                            $middleware,
                            $kernel,
                            $this
                        );
                    }
                }
            }
            $manager?->dispatch(
                'kernel.registerMiddlewares',
                $kernel,
                $this
            );
        } finally {
            $manager?->dispatch(
                'kernel.afterRegisterMiddlewares',
                $kernel,
                $this
            );
        }
    }
}
