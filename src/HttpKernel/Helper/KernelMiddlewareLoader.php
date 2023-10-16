<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Middleware\AbstractMiddleware;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;

final class KernelMiddlewareLoader extends AbstractLoaderNameBased
{
    protected function getNameSpace(): ?string
    {
        return $this->kernel->getMiddlewareNamespace();
    }

    /**
     * @return Finder
     */
    protected function getFileLists(): Finder
    {
        return $this
            ->createFinder($this->getDirectory(), 0, '/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
            ->files();
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
        return 'middlewares';
    }

    protected function isProcessable(): bool
    {
        return $this->getNameSpace()
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
        // @dispatch(kernel.beforeRegisterMiddleware)
        $manager?->dispatch(
            'kernel.beforeRegisterMiddleware',
            $realPath,
            $this->kernel->getHttpKernel(),
            $this->kernel
        );
        $result = null;
        try {
            $className = $this->getClasNameFromFile($splFileInfo);
            if (!$className) {
                // @dispatch(kernel.registerMiddleware)
                $manager?->dispatch(
                    'kernel.registerMiddleware',
                    $realPath,
                    $this->kernel->getHttpKernel(),
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
                    && $ref->isSubclassOf(AbstractMiddleware::class)
                    && $ref->getFileName() === $splFileInfo->getRealPath()
                ) {
                    $result = ContainerHelper::resolveCallable($className, $this->getContainer());
                    $this->kernel->getHttpKernel()->addMiddleware($result);
                }
            } catch (Throwable $e) {
                $this->getLogger()?->debug(
                    $e,
                    [
                        'loaderMode' => 'middleware',
                        'classLoader' => $this::class,
                        'className' => $className,
                        'file' => $splFileInfo->getRealPath()
                    ]
                );
            }
            // @dispatch(kernel.registerMiddleware)
            $manager?->dispatch(
                'kernel.registerMiddleware',
                $realPath,
                $this->kernel->getHttpKernel(),
                $this->kernel,
                $result
            );
        } finally {
            if ($result) {
                $this->injectDependency($result);
            }
            // @dispatch(kernel.afterRegisterMiddleware)
            $manager?->dispatch(
                'kernel.afterRegisterMiddleware',
                $realPath,
                $this->kernel->getHttpKernel(),
                $this->kernel,
                $result
            );
        }
    }
}
