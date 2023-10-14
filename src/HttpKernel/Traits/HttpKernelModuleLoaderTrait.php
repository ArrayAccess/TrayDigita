<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Traits;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Module\Interfaces\ModuleInterface;
use ArrayAccess\TrayDigita\Module\Modules;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function array_filter;
use function dirname;
use function is_array;
use function is_dir;
use function is_string;
use function md5;
use function sprintf;
use const ARRAY_FILTER_USE_BOTH;

trait HttpKernelModuleLoaderTrait
{
    protected function registerModules() : void
    {
        if ($this->moduleRegistered) {
            return;
        }

        $moduleNameSpace = $this->moduleNameSpace;
        if (!$moduleNameSpace) {
            return;
        }

        $moduleDirectory = $this->registeredDirectories[$moduleNameSpace]??null;
        if (!is_string($moduleDirectory) || !is_dir($moduleDirectory)) {
            return;
        }

        try {
            $modules = ContainerHelper::decorate(
                Modules::class,
                $this->getHttpKernel()->getContainer()
            );
        } catch (Throwable) {
            $modules = ContainerHelper::service(
                Modules::class,
                $this->getHttpKernel()->getContainer()
            );
        }

        if (!$modules) {
            return;
        }

        $this->moduleRegistered = true;
        // back compat! that means provider should call before module
        if (!$this->providerRegistered) {
            // do register providers
            $this->registerProviders();
        }

        $manager = ContainerHelper::use(
            ManagerInterface::class,
            $this->getHttpKernel()->getContainer()
        );
        if (!$manager && $modules->getManager()) {
            $manager = $modules->getManager();
        } elseif ($manager && !$modules->getManager()) {
            $modules->setManager($manager);
        }

        $manager?->dispatch(
            'kernel.beforeRegisterModules',
            $this
        );
        try {
            /*
            $cacheData = $this->getInternalCacheData(
                $moduleDirectory,
                $moduleNameSpace
            );

            $cacheName = $cacheData['name']??null;
            $mtime = $cacheData['time']??null;
            $cacheItem = $cacheData['item']??null;
            $cacheData = $cacheData['data']??null;
            $moduleRegistered = false;
            if ($cacheData !== null) {
                $moduleRegistered = true;
                foreach ($cacheData as $moduleName) {
                    if (!is_string($moduleName)
                        || !is_subclass_of(
                            $moduleName,
                            ModuleInterface::class
                        )
                    ) {
                        $moduleRegistered = false;
                        break;
                    }
                }
                if ($moduleRegistered) {
                    foreach ($cacheData as $moduleName) {
                        try {
                            $modules->attach($moduleName);
                        } catch (Throwable) {
                        }
                    }
                }
            }

            if ($moduleRegistered) {
                $manager?->dispatch(
                    'kernel.registerModules',
                    $this
                );
                return;
            }
            $cacheData = [];
            */
            $times = '';
            $directories = Finder::create()
                ->in($moduleDirectory)
                ->ignoreVCS(true)
                ->ignoreDotFiles(true)
                ->depth(0)
                ->name('/^[_A-za-z]([a-zA-Z0-9]+)?$/')
                ->directories();
            foreach ($directories as $splFileInfo) {
                $times .= $splFileInfo->getMTime() . $splFileInfo->getBasename();
            }
            $hash = md5($moduleDirectory . $times);
            $cacheGlobalKey = 'kernel_modules_'. md5($moduleNameSpace);
            $cacheItemGlobal = $this->getCacheItem($cacheGlobalKey);
            $cacheData = $cacheItemGlobal?->get();
            $toLoad = [];
            $includes = [];
            /** @noinspection DuplicatedCode */
            if (is_array($cacheData)
                && is_array($cacheData['list']??null)
                && is_array($cacheData['directories']??null)
                && is_array($cacheData['includes']??null)
                && is_string($cacheData['hash']??null)
                && ($cacheData['key']??null) === $cacheGlobalKey
                && ($cacheData['namespace']??null) === $moduleNameSpace
                && $hash === $cacheData['hash']
            ) {
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
                foreach ($cacheData['list'] as $className) {
                    $modules->attach($className);
                }
                if ($cacheData['directories'] !== []) {
                    $this->registeredDirectories = $cacheData['directories']
                        + $this->registeredDirectories;
                }
            } else {
                $moduleBasedName = 'Module';
                $registeredDirectory = [];
                foreach ($directories as $file) {
                    $module = null;
                    $name = $file->getBasename();
                    $module = $this->internalLoadModuleClassName(
                        new SplFileInfo("$file/$name.php"),
                        $modules,
                        $reflectionClass,
                        $include
                    );
                    $module ??= $this->internalLoadModuleClassName(
                        new SplFileInfo("$file/$moduleBasedName.php"),
                        $modules,
                        $reflectionClass,
                        $include
                    );
                    if ($module) {
                        if ($reflectionClass instanceof ReflectionClass
                            && ($ns = $reflectionClass->getNamespaceName())
                        ) {
                            $fileName = $reflectionClass->getFileName();
                            $toLoad[$fileName] = $reflectionClass->getName();
                            if ($include) {
                                $includes[$fileName] = true;
                                $ns = $ns . '\\';
                                $registeredDirectory[$ns] = dirname($fileName);
                            }
                        }
                        continue;
                    }
                    foreach (Finder::create()
                                 ->in($file->getRealPath())
                                 ->depth(0)
                                 ->ignoreVCS(true)
                                 ->ignoreDotFiles(true)
                                 // accept standard class name
                                 ->name('/^[A-z]([a-zA-Z0-9]+)?\.php$/')
                                 ->files() as $splFileInfo
                    ) {
                        if ($splFileInfo->getBasename() === "$name.php"
                            || $splFileInfo->getBasename() === "$moduleBasedName.php"
                        ) {
                            continue;
                        }
                        $module = $this->internalLoadModuleClassName(
                            $splFileInfo,
                            $modules,
                            $reflectionClass
                        );
                        if ($module) {
                            if ($reflectionClass && ($ns = $reflectionClass->getNamespaceName())) {
                                $fileName = $reflectionClass->getFileName();
                                $toLoad[$fileName] = $reflectionClass->getName();
                                if ($include) {
                                    $includes[$fileName] = true;
                                    $ns = $ns . '\\';
                                    $registeredDirectory[$ns] = dirname($fileName);
                                }
                            }
                            break;
                        }
                    }
                }
                if ($cacheItemGlobal) {
                    $cacheData = [
                        'hash' => $hash,
                        'key' => $cacheGlobalKey,
                        'namespace' => $moduleNameSpace,
                        'list' => $toLoad,
                        'directories' => $registeredDirectory,
                        'includes' => $includes,
                    ];
                    $cacheItemGlobal->set($cacheData);
                    $cacheItemGlobal->expiresAfter(self::DEFAULT_EXPIRED_AFTER);
                    $this->saveCacheItem($cacheItemGlobal);
                }
            }

            // $this->saveInternalCacheData($cacheName, $mtime, $cacheItem, $cacheData);
            $manager?->dispatch(
                'kernel.registerModules',
                $this
            );
        } finally {
            $manager?->dispatch(
                'kernel.afterRegisterModules',
                $this
            );
            $manager?->dispatch(
                'kernel.beforeInitModules',
                $this
            );
            try {
                // init modules
                $modules->initModules();
                $manager?->dispatch(
                    'kernel.initModules',
                    $this
                );
            } finally {
                $manager?->dispatch(
                    'kernel.afterInitModules',
                    $this
                );
            }
        }
    }

    /**
     * @param SplFileInfo $splFileInfo
     * @param Modules $modules
     * @param null $reflectionClass
     * @param null $include
     * @return ?ModuleInterface
     */
    private function internalLoadModuleClassName(
        SplFileInfo $splFileInfo,
        Modules $modules,
        &$reflectionClass = null,
        &$include = null
    ) : ?ModuleInterface {
        $include = false;
        $realPath = $splFileInfo->getRealPath();
        // no include if size more than 500kb
        if (!$splFileInfo->isFile() || $splFileInfo->getSize() > 512000) {
            return null;
        }
        $manager = $this->getManager();
        $manager?->dispatch('kernel.beforeLoadModule', $realPath, $this);
        $cacheKey = sprintf('kernel_module_%s', md5($realPath));
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
                return null;
            }

            try {
                $ref = new ReflectionClass($className);
                $succeed = true;
            } catch (ReflectionException) {
                (function ($realPath) {
                    require_once $realPath;
                })($realPath);
                $succeed = false;
            }
            $ref ??= new ReflectionClass($className);
            $className = $ref->getName();
            if ($rewrite && $cacheItem) {
                $cacheItems['className'] = $className;
                $cacheItem->set($cacheItems);
                $this->saveCacheItem($cacheItem);
            }
            try {
                if ($ref->getFileName() === $realPath
                    && $ref->isInstantiable()
                    && $ref->isSubclassOf(ModuleInterface::class)
                ) {
                    try {
                        $result = $modules->attach($ref->getName());
                        $reflectionClass = $ref;
                        $include = $succeed === false;
                        return $result??null;
                    } catch (Throwable) {
                        return null;
                    }
                }
            } finally {
                $manager?->dispatch(
                    'kernel.loadModule',
                    $realPath,
                    $this,
                    $result??null
                );
            }
        } catch (Throwable) {
        } finally {
            $manager?->dispatch(
                'kernel.afterLoadModule',
                $realPath,
                $this,
                $result??null
            );
        }
        return null;
    }
}
