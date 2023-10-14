<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Traits;

use ArrayAccess\TrayDigita\Console\AbstractCommand;
use ArrayAccess\TrayDigita\Console\Application;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;
use function is_array;
use function is_dir;
use function is_string;
use function is_subclass_of;
use function md5;
use function method_exists;
use function sprintf;
use function ucfirst;

trait HttpKernelSchedulerCommandTrait
{
    /**
     * @param ?string $namespace
     * @param string $application
     * @param string $objectProperty
     * @param string $eventName
     * @param string $parentClass
     * @param string|null $methodBefore
     * @param string|null $propertyBefore
     * @return void
     * @throws InvalidArgumentException
     * @throws Throwable
     * @throws ReflectionException
     * @internal
     *
     * @see registerSchedulers()
     * @see registerCommands()
     */
    private function registerInternalSingleServiceAddApplication(
        ?string $namespace,
        string $application,
        string $objectProperty,
        string $eventName,
        string $parentClass,
        ?string $methodBefore = null,
        ?string $propertyBefore = null
    ): void {
        if (!is_string($namespace)) {
            return;
        }
        $directory = $this->registeredDirectories[$namespace]??null;
        if (!is_string($directory) || !is_dir($directory)) {
            return;
        }
        $app = ContainerHelper::use($application);
        if (!$app) {
            return;
        }

        $this->$objectProperty = true;
        $upperName = ucfirst($eventName);
        // just back compat -> services should register before commands
        if ($propertyBefore
            && $methodBefore
            && isset($this->$propertyBefore)
            && $this->$propertyBefore === false
            && method_exists($this, $methodBefore)
        ) {
            $this->{$methodBefore}();
        }

        $manager = ContainerHelper::use(
            ManagerInterface::class,
            $this->getHttpKernel()->getContainer()
        );
        $manager?->dispatch(
            "kernel.beforeRegister$upperName",
            $this
        );

        $loadClasses = function (
            SplFileInfo $splFileInfo,
            $application,
            string $namespace,
            string $parentClass
        ) {
            $realPath = $splFileInfo->getRealPath();
            if (!$realPath || !$splFileInfo->isFile()) {
                return null;
            }

            $cacheKey = sprintf('kernel_scheduler_%s', md5($realPath));
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
                if (!class_exists($className)) {
                    (function ($realPath) {
                        require_once $realPath;
                    })($realPath);
                }

                $ref = new ReflectionClass($className);
                $className = $ref->getName();
                if ($rewrite && $cacheItem) {
                    $cacheItems['className'] = $className;
                    $cacheItem->set($cacheItems);
                    $this->saveCacheItem($cacheItem);
                }
                if ($ref->getFileName() === $realPath
                    && $ref->isInstantiable()
                    && $ref->isSubclassOf($parentClass)
                ) {
                    try {
                        $task = new $className($application);
                        if ($application instanceof ContainerIndicateInterface
                            && $ref->isSubclassOf(ContainerAllocatorInterface::class)
                            && $application->getContainer()
                        ) {
                            $task->setContainer($application->getContainer());
                        }
                        if ($application instanceof ManagerIndicateInterface
                            && $ref->isSubclassOf(ManagerAllocatorInterface::class)
                            && $application->getManager()
                        ) {
                            $task->setManager($application->getManager());
                        }
                        $application->{'add'}($task);
                        return $task;
                    } catch (Throwable) {
                        return null;
                    }
                }
            } catch (Throwable) {
            }
            return null;
        };

        try {
            $cacheData = $this->getInternalCacheData(
                $directory,
                $namespace
            );
            $mtime = $cacheData['time']??null;
            $cacheName = $cacheData['name']??null;
            $cacheItem = $cacheData['item']??null;
            $cacheData = $cacheData['data']??null;
            $registered = false;
            if (is_array($cacheData)) {
                $registered = true;
                foreach ($cacheData as $command) {
                    if (!is_string($command)
                        || !is_subclass_of(
                            $command,
                            $parentClass
                        )
                    ) {
                        $registered = false;
                        break;
                    }
                }

                if ($registered) {
                    $appContainer = $app instanceof ContainerIndicateInterface
                        ? $app->getContainer()
                        : null;
                    $appManager = $app instanceof ManagerIndicateInterface
                        ? $app->getManager()
                        : null;
                    foreach ($cacheData as $commandClassName) {
                        try {
                            $task = new $commandClassName($app);
                            if ($appContainer && $task instanceof ContainerAllocatorInterface) {
                                $task->setContainer($appContainer);
                            }
                            if ($appManager && $task instanceof ManagerAllocatorInterface) {
                                $task->setManager($appManager);
                            }
                            $app->{'add'}($task);
                        } catch (Throwable) {
                        }
                    }
                }
            }

            if ($registered) {
                $manager?->dispatch(
                    "kernel.register$upperName",
                    $this
                );
                return;
            }

            $cacheData = [];
            foreach (Finder::create()
                         ->in($directory)
                         ->ignoreVCS(true)
                         ->ignoreDotFiles(true)
                         ->depth(0)
                         ->name('/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
                         ->files() as $file) {
                $command = $loadClasses($file, $app, $namespace, $parentClass);
                if ($command) {
                    $command = $command::class;
                    $cacheData[] = $command;
                }
            }

            unset($loadClasses);
            $this->saveInternalCacheData(
                $cacheName,
                $mtime,
                $cacheItem,
                $cacheData,
                $directory,
                $namespace
            );
            $manager?->dispatch(
                "kernel.register$upperName",
                $this
            );
        } finally {
            $manager?->dispatch(
                "kernel.afterRegister$upperName",
                $this
            );
        }
    }

    /**
     * @uses registerInternalSingleServiceAddApplication()
     */
    protected function registerCommands() : void
    {
        if ($this->commandRegistered) {
            return;
        }

        $this->commandRegistered = true;
        $this->registerInternalSingleServiceAddApplication(
            namespace: $this->commandNameSpace,
            application: Application::class,
            objectProperty: 'commandRegistered',
            eventName: 'commands',
            parentClass: AbstractCommand::class,
            methodBefore: 'registerServices',
            propertyBefore: 'serviceRegistered'
        );
    }

    /**
     * @uses registerInternalSingleServiceAddApplication()
     */
    protected function registerSchedulers() : void
    {
        if ($this->schedulerRegistered) {
            return;
        }

        $this->schedulerRegistered = true;
        $this->registerInternalSingleServiceAddApplication(
            namespace: $this->schedulerNamespace,
            application: Scheduler::class,
            objectProperty: 'schedulerRegistered',
            eventName: 'Schedulers',
            parentClass: Task::class,
            methodBefore: 'registerModules',
            propertyBefore: 'moduleRegistered'
        );
    }
}
