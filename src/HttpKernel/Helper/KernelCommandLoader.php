<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Console\AbstractCommand;
use ArrayAccess\TrayDigita\Console\Application;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;
use function is_bool;
use function is_dir;

class KernelCommandLoader extends AbstractLoaderNameBased
{
    private ?Application $scheduler = null;

    protected function getNameSpace(): ?string
    {
        return $this->kernel->getCommandNameSpace();
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
            ->createFinder($this->getDirectory(), 0, '/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
            ->files();
    }

    protected function getApplication() : ?Application
    {
        return $this->scheduler ??= ContainerHelper::service(
            Application::class,
            $this->kernel->getHttpKernel()->getContainer()
        );
    }

    protected function getManager(): ?ManagerInterface
    {
        return $this->getApplication()
            ?->getManager()??parent::getManager();
    }

    protected function getContainer(): ?ContainerInterface
    {
        return $this->getApplication()
            ?->getContainer()??parent::getContainer();
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
        return 'commands';
    }

    protected function isProcessable(): bool
    {
        $processable = $this->getNameSpace()
            && $this->getDirectory()
            && $this->getApplication();
        if ($processable) {
            $canBeProcess = $this
                ->getManager()
                ->dispatch('kernel.commandLoader', true);
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
        if (!$splFileInfo->isFile()
            || ! ($application = $this->getApplication())
        ) {
            return;
        }
        $realPath = $splFileInfo->getRealPath();
        $manager = $this->getManager();
        // @dispatch(kernel.beforeRegisterCommand)
        $manager?->dispatch(
            'kernel.beforeRegisterCommand',
            $realPath,
            $application,
            $this->kernel
        );
        $result = null;
        try {
            $className = $this->getClasNameFromFile($splFileInfo);
            if (!$className) {
                // @dispatch(kernel.registerCommand)
                $manager?->dispatch(
                    'kernel.registerCommand',
                    $realPath,
                    $application,
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
                    && $ref->isSubclassOf(AbstractCommand::class)
                    && $ref->getFileName() === $splFileInfo->getRealPath()
                ) {
                    $result = new $className($application);
                    $application->add($result);
                }
            } catch (Throwable $e) {
                $this->getLogger()?->debug(
                    $e,
                    [
                        'loaderMode' => 'command',
                        'classLoader' => $this::class,
                        'className' => $className,
                        'file' => $splFileInfo->getRealPath()
                    ]
                );
            }
            // @dispatch(kernel.registerCommand)
            $manager?->dispatch(
                'kernel.registerCommand',
                $realPath,
                $application,
                $this->kernel,
                $result
            );
        } finally {
            if ($result) {
                $this->injectDependency($result);
            }
            // @dispatch(kernel.afterRegisterCommand)
            $manager?->dispatch(
                'kernel.afterRegisterCommand',
                $realPath,
                $application,
                $this->kernel,
                $result
            );
        }
    }
}
