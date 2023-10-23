<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Routing\Interfaces\ControllerInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;
use function is_bool;
use function is_dir;
use function trim;
use function ucwords;

class KernelControllerLoader extends AbstractLoaderNameBased
{
    protected function getNameSpace(): ?string
    {
        return $this->kernel->getControllerNameSpace();
    }
    protected function doRegister(): bool
    {
        if (!$this->isProcessable()) {
            return false;
        }

        // preprocess
        $this->preProcess();
        $mode = ucwords(trim($this->getMode()));
        $manager = $this->getManager();
        $mode && $manager?->dispatch(
            "kernel.beforeRegisterControllers",
            $this->kernel
        );
        try {
            $maxDepth = 20;
            foreach ($this->kernel->getControllersDirectories() as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                foreach ($this
                    ->createFinder(
                        $dir,
                        "<= $maxDepth",
                        '/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/'
                    )
                    ->files() as $file) {
                    $this->loadService($file);
                }
            }
            $mode && $manager?->dispatch(
                "kernel.registerControllers",
                $this->kernel
            );
        } finally {
            $mode && $manager?->dispatch(
                "kernel.afterRegisterControllers",
                $this->kernel
            );

            // postprocess
            $this->postProcess();
        }
        return true;
    }

    /**
     * @return ?Finder
     */
    protected function getFileLists(): ?Finder
    {
        return null;
    }

    /**
     * @return RouterInterface
     */
    public function getRouter() : RouterInterface
    {
        return $this->kernel->getHttpKernel()->getRouter();
    }

    protected function getContainer(): ContainerInterface
    {
        return parent::getContainer()??Decorator::container();
    }

    protected function getMode(): string
    {
        return 'controllers';
    }

    protected function isProcessable(): bool
    {
        $processable = $this->getNameSpace()
            && ! $this->kernel->getConfigError();
        if ($processable) {
            $canBeProcess = $this
                ->getManager()
                ->dispatch('kernel.controllerLoader', true);
            $processable = is_bool($canBeProcess) ? $canBeProcess : true;
        }
        return $processable;
    }

    /**
     * @param SplFileInfo $splFileInfo
     * @return void
     */
    protected function loadService(
        SplFileInfo $splFileInfo
    ): void {
        if (!$splFileInfo->isFile()) {
            return;
        }
        $realPath = $splFileInfo->getRealPath();
        $manager = $this->getManager();
        // @dispatch(kernel.beforeRegisterController)
        $manager?->dispatch(
            'kernel.beforeRegisterController',
            $realPath,
            $this->getRouter(),
            $this->kernel,
            $this
        );
        $result = null;
        try {
            $className = $this->getClasNameFromFile($splFileInfo);
            if (!$className) {
                // @dispatch(kernel.registerController)
                $manager?->dispatch(
                    'kernel.registerController',
                    $realPath,
                    $this->getRouter(),
                    $this->kernel,
                    $this
                );
                return;
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
                    && $ref->isSubclassOf(ControllerInterface::class)
                    && $ref->getFileName() === $splFileInfo->getRealPath()
                ) {
                    $result = $this->getRouter()->addRouteController($ref->getName());
                }
            } catch (Throwable $e) {
                $this->getLogger()?->debug(
                    $e,
                    [
                        'loaderMode' => 'controller',
                        'classLoader' => $this::class,
                        'className' => $className,
                        'file' => $splFileInfo->getRealPath()
                    ]
                );
            }
            // @dispatch(kernel.registerController)
            $manager?->dispatch(
                'kernel.registerController',
                $realPath,
                $this->getRouter(),
                $this->kernel,
                $this,
                $result
            );
        } finally {
            // @dispatch(kernel.afterRegisterController)
            $manager?->dispatch(
                'kernel.afterRegisterController',
                $realPath,
                $this->getRouter(),
                $this->kernel,
                $this,
                $result
            );
        }
    }
}
