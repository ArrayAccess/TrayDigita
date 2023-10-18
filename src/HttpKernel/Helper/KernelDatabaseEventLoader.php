<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Database\DatabaseEventsCollector;
use ArrayAccess\TrayDigita\Database\Interfaces\DatabaseEventInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;
use function is_bool;
use function is_dir;

class KernelDatabaseEventLoader extends AbstractLoaderNameBased
{
    private ?DatabaseEventsCollector $databaseEventsCollector = null;

    protected function getNameSpace(): ?string
    {
        return $this->kernel->getDatabaseEventNameSpace();
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

    protected function getDatabaseEventsCollector() : ?DatabaseEventsCollector
    {
        return $this->databaseEventsCollector ??= ContainerHelper::service(
            DatabaseEventsCollector::class,
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
        return 'databaseEvents';
    }

    protected function isProcessable(): bool
    {
        $processable = ! $this->kernel->getConfigError()
            && $this->getNameSpace()
            && $this->getDirectory()
            && $this->getDatabaseEventsCollector();
        if ($processable) {
            $canBeProcess = $this
                ->getManager()
                ->dispatch('kernel.databaseEventLoader', true);
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
            || ! ($eventsCollector = $this->getDatabaseEventsCollector())
        ) {
            return;
        }
        $realPath = $splFileInfo->getRealPath();
        $manager = $this->getManager();
        // @dispatch(kernel.beforeRegisterDatabaseEvent)
        $manager?->dispatch(
            'kernel.beforeRegisterDatabaseEvent',
            $realPath,
            $eventsCollector,
            $this->kernel
        );
        $result = null;
        try {
            $className = $this->getClasNameFromFile($splFileInfo);
            if (!$className) {
                // @dispatch(kernel.registerDatabaseEvent)
                $manager?->dispatch(
                    'kernel.registerDatabaseEvent',
                    $realPath,
                    $eventsCollector,
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
                    && $ref->isSubclassOf(DatabaseEventInterface::class)
                    && $ref->getFileName() === $splFileInfo->getRealPath()
                ) {
                    $result = $eventsCollector->add($ref->getName());
                }
            } catch (Throwable $e) {
                $this->getLogger()?->debug(
                    $e,
                    [
                        'loaderMode' => 'databaseEvent',
                        'classLoader' => $this::class,
                        'className' => $className,
                        'file' => $splFileInfo->getRealPath()
                    ]
                );
            }
            // @dispatch(kernel.registerDatabaseEvent)
            $manager?->dispatch(
                'kernel.registerDatabaseEvent',
                $realPath,
                $eventsCollector,
                $this->kernel,
                $result
            );
        } finally {
            // @dispatch(kernel.afterRegisterDatabaseEvent)
            $manager?->dispatch(
                'kernel.afterRegisterDatabaseEvent',
                $realPath,
                $eventsCollector,
                $this->kernel,
                $result
            );
        }
    }
}
