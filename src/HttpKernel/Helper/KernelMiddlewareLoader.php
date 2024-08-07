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
use function is_bool;
use function is_dir;
use function trim;
use function ucfirst;

class KernelMiddlewareLoader extends AbstractLoaderNameBased
{
    protected function getNameSpace(): ?string
    {
        return $this->kernel->getMiddlewareNamespace();
    }

    /**
     * @return ?Finder
     */
    protected function getFileLists(): ?Finder
    {
        $directory = $this->getDirectory();
        return !$directory || !is_dir($directory)
            ? null
            : $this
                ->createFinder($this->getDirectory(), 0, '/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
                ->files();
    }

    protected function getDirectory(): ?string
    {
        $namespace = $this->getNameSpace();
        $directory = $namespace
            ? $this->kernel->getRegisteredDirectories()[$namespace] ?? null
            : null;
        return $directory && is_dir($directory) ? $directory : null;
    }

    protected function getContainer(): ContainerInterface
    {
        return parent::getContainer() ?? Decorator::container();
    }

    protected function getMode(): string
    {
        return 'middlewares';
    }

    protected function isProcessable(): bool
    {
        $processable = $this->getNameSpace()
            && $this->getDirectory();
        if ($processable) {
            $canBeProcess = $this
                ->getManager()
                ->dispatch('kernel.middlewareLoader', true);
            $processable = is_bool($canBeProcess) ? $canBeProcess : true;
        }
        return $processable;
    }

    /* *
     * @var array<int, array<string, AbstractMiddleware>>
     * @return bool
     */
    // private array $middlewares = [];

    /*protected function postProcess(): void
    {
        $this->kernel->getHttpKernel()->dispatchDeferredMiddleware();
    }*/

    /**
     * Do register middleware
     *
     * @return bool
     */
    protected function doRegister(): bool
    {
        // preprocess
        $this->preProcess();
        $files = $this->getFileLists();
        if (!$files) {
            return false;
        }

        $mode = ucfirst(trim($this->getMode()));
        $manager = $this->getManager();
        $mode && $manager?->dispatch(
            "kernel.beforeRegister$mode",
            $this->kernel->getHttpKernel(),
            $this->kernel,
            $this
        );
        try {
            foreach ($files as $list) {
                $this->loadService($list);
            }
            // postprocess
            $this->postProcess();
            $mode && $manager?->dispatch(
                "kernel.register$mode",
                $this->kernel->getHttpKernel(),
                $this->kernel,
                $this
            );
        } finally {
            $mode && $manager?->dispatch(
                "kernel.afterRegister$mode",
                $this->kernel->getHttpKernel(),
                $this->kernel,
                $this
            );
        }
        return true;
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
        $httpKernel = $this->kernel->getHttpKernel();
        $realPath = $splFileInfo->getRealPath();
        $manager = $this->getManager();
        // @dispatch(kernel.beforeLoadMiddleware)
        $manager?->dispatch(
            'kernel.beforeLoadMiddleware',
            $realPath,
            $httpKernel,
            $this->kernel,
            $this
        );
        $result = null;
        try {
            $className = $this->getClasNameFromFile($splFileInfo);
            if (!$className) {
                // @dispatch(kernel.registerMiddleware)
                $manager?->dispatch(
                    'kernel.loadMiddleware',
                    $realPath,
                    $httpKernel,
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
                    && $ref->isSubclassOf(AbstractMiddleware::class)
                    && $ref->getFileName() === $splFileInfo->getRealPath()
                ) {
                    $result = ContainerHelper::resolveCallable($className, $this->getContainer());
                    if ($result instanceof AbstractMiddleware) {
                        $httpKernel->addDeferredMiddleware($result);
                    }
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
            // @dispatch(kernel.loadMiddleware)
            $manager?->dispatch(
                'kernel.loadMiddleware',
                $realPath,
                $httpKernel,
                $this->kernel,
                $this,
                $result
            );
        } finally {
            if ($result) {
                $this->injectDependency($result);
            }
            // @dispatch(kernel.afterLoadMiddleware)
            $manager?->dispatch(
                'kernel.afterLoadMiddleware',
                $realPath,
                $httpKernel,
                $this->kernel,
                $this,
                $result
            );
        }
    }
}
