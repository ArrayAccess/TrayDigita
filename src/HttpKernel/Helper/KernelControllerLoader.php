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

final class KernelControllerLoader extends AbstractLoaderNameBased
{
    protected function getNameSpace(): ?string
    {
        return $this->kernel->getControllerNameSpace();
    }

    /**
     * @return Finder
     */
    protected function getFileLists(): Finder
    {
        // override
        $maxDepth = 20;
        return $this
            ->createFinder($this->getDirectory(), "<= $maxDepth", '/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
            ->files();
    }

    /**
     * @return RouterInterface
     */
    public function getRouter() : RouterInterface
    {
        return $this->kernel->getHttpKernel()->getRouter();
    }

    protected function getDirectory(): ?string
    {
        $namespace = $this->getNameSpace();
        return $namespace
            ? $this->kernel->getRegisteredDirectories()[$namespace]??null
            : null;
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
        return ! $this->kernel->getConfigError()
            && $this->getNameSpace()
            && $this->getDirectory();
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
            $this->kernel
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
                    $this->kernel
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
                $result
            );
        } finally {
            // @dispatch(kernel.afterRegisterController)
            $manager?->dispatch(
                'kernel.afterRegisterController',
                $realPath,
                $this->getRouter(),
                $this->kernel,
                $result
            );
        }
    }
}
