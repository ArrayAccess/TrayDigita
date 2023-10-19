<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Module\Interfaces\ModuleInterface;
use ArrayAccess\TrayDigita\Module\Modules;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;
use function is_bool;
use function is_dir;

class KernelModuleLoader extends AbstractLoaderNameBased
{
    private ?Modules $modules = null;

    protected function getNameSpace(): ?string
    {
        return $this->kernel->getModuleNameSpace();
    }

    /**
     * @return ?Finder
     */
    protected function getFileLists(): ?Finder
    {
        $directory = $this->getDirectory();
        return !$directory || ! is_dir($directory)
            ? null
            : $this
            ->createFinder($this->getDirectory(), 0, '/^[_A-za-z]([a-zA-Z0-9]+)?$/')
            ->directories();
    }

    protected function getModules() : ?Modules
    {
        return $this->modules ??= ContainerHelper::service(
            Modules::class,
            $this->kernel->getHttpKernel()->getContainer()
        );
    }

    protected function getDirectory(): ?string
    {
        $namespace = $this->getNameSpace();
        $directory =  $namespace
            ? $this->kernel->getRegisteredDirectories()[$namespace]??null
            : null;
        return $directory && is_dir($directory) ? $directory : null;
    }

    protected function getMode(): string
    {
        return 'modules';
    }

    protected function getManager(): ?ManagerInterface
    {
        return $this->getModules()?->getManager()??parent::getManager();
    }

    /**
     * @return ContainerInterface|null
     */
    protected function getContainer(): ?ContainerInterface
    {
        return $this->getModules()?->getContainer()??parent::getContainer();
    }

    protected function isProcessable(): bool
    {
        $processable = $this->getNameSpace()
            && $this->getDirectory()
            && $this->getModules();
        if ($processable) {
            $canBeProcess = $this
                ->getManager()
                ->dispatch('kernel.moduleLoader', true);
            $processable = is_bool($canBeProcess) ? $canBeProcess : true;
        }
        return $processable;
    }

    /**
     * @return void
     */
    protected function postProcess(): void
    {
        $modules = $this->getModules();
        if (!$modules) {
            return;
        }
        $manager = $this->getManager();
        // @dispatch(kernel.beforeInitModules);
        $manager?->dispatch(
            'kernel.beforeInitModules',
            $modules,
            $this->kernel,
            $this
        );
        try {
            // init modules
            $modules->initModules();
            // @dispatch(kernel.initModules);
            $manager?->dispatch(
                'kernel.initModules',
                $modules,
                $this->kernel,
                $this
            );
        } finally {
            // @dispatch(kernel.afterInitModules);
            $manager?->dispatch(
                'kernel.afterInitModules',
                $modules,
                $this->kernel,
                $this
            );
        }
    }

    /**
     * @param SplFileInfo $splFileInfo
     * @param Modules $modules
     * @return ?ModuleInterface
     */
    private function doingLoadModule(
        SplFileInfo $splFileInfo,
        Modules $modules
    ): ?ModuleInterface {
        $className = $this->getClasNameFromFile($splFileInfo);
        if (!$className) {
            return null;
        }
        try {
            if (!class_exists($className)) {
                (static function ($realPath) {
                    require_once $realPath;
                })($splFileInfo->getRealPath());
            }
            $ref = new ReflectionClass($className);
            if ($ref->getName() !== $className) {
                $this->saveClasNameFromFile(
                    $splFileInfo,
                    $ref->getName()
                );
            }
            if ($ref->isInstantiable()
                && $ref->isSubclassOf(ModuleInterface::class)
                && $ref->getFileName() === $splFileInfo->getRealPath()
            ) {
                return $modules->attach($ref->getName());
            }
        } catch (Throwable $e) {
            $this->getLogger()?->debug(
                $e,
                [
                    'loaderMode' => 'module',
                    'classLoader' => $this::class,
                    'className' => $className,
                    'file' => $splFileInfo->getRealPath()
                ]
            );
        }

        return null;
    }

    /**
     * @param SplFileInfo $splFileInfo
     * @return void
     */
    protected function loadService(
        SplFileInfo $splFileInfo
    ): void {
        if (!$splFileInfo->isDir() || ! ($modules = $this->getModules())) {
            return;
        }
        $realPath = $splFileInfo->getRealPath();
        $manager = $this->getManager();
        // @dispatch(kernel.beforeRegisterModule)
        $manager?->dispatch(
            'kernel.beforeRegisterModule',
            $realPath,
            $modules,
            $this->kernel,
            $this
        );
        $result = null;
        $baseName = $splFileInfo->getBasename();
        try {
            $spl = new SplFileInfo("$realPath/$baseName.php");
            if ($spl->isFile()) {
                $result = $this->doingLoadModule($spl, $modules);
                // @dispatch(kernel.registerModule)
                $manager?->dispatch(
                    'kernel.registerModule',
                    $realPath,
                    $modules,
                    $this->kernel,
                    $this,
                    $result
                );
                return;
            }

            if (($targetFile = $this->getCacheItemFileDirectory($splFileInfo)) !== null) {
                if ($targetFile === false) {
                    return;
                }
                if ($targetFile->getBasename() !== $spl->getBasename()
                    && $targetFile->isFile()
                ) {
                    $result = $this->doingLoadModule($targetFile, $modules);
                    if ($result) {
                        // @dispatch(kernel.registerModule)
                        $manager?->dispatch(
                            'kernel.registerModule',
                            $realPath,
                            $modules,
                            $this->kernel,
                            $this,
                            $result
                        );
                        $this->saveCacheItemFileDirectory($splFileInfo, $targetFile);
                    }
                    unset($spl);
                }
            }

            // doing loop
            foreach ($this
                 ->createFinder(
                     $realPath,
                     0,
                     '/^[A-z]([a-zA-Z0-9]+)?\.php$/'
                 )->files() as $spl
            ) {
                if ($spl->getExtension() !== 'php' || "$baseName.php" === $spl->getBasename()) {
                    continue;
                }
                $result = $this->doingLoadModule($spl, $modules);
                if (!$result) {
                    continue;
                }
                $this->saveCacheItemFileDirectory($splFileInfo, $spl);
                // @dispatch(kernel.registerModule)
                $manager?->dispatch(
                    'kernel.registerModule',
                    $realPath,
                    $modules,
                    $this->kernel,
                    $this,
                    $result
                );
                return;
            }

            $this->saveCacheItemFileDirectory($splFileInfo);
            // @dispatch(kernel.registerModule)
            $manager?->dispatch(
                'kernel.registerModule',
                $realPath,
                $modules,
                $this->kernel,
                $this,
                null
            );
        } finally {
            if ($result) {
                $this->registerAutoloader(
                    $result,
                    $this->kernel->getModuleNameSpace(),
                    $this->getDirectory()
                );
                $this->injectDependency($result);
            }

            // @dispatch(kernel.afterRegisterModule)
            $manager?->dispatch(
                'kernel.afterRegisterModule',
                $realPath,
                $modules,
                $this->kernel,
                $this,
                $result
            );
        }
    }
}
