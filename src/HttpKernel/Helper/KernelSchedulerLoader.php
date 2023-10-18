<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;
use function is_bool;
use function is_dir;

class KernelSchedulerLoader extends AbstractLoaderNameBased
{
    private ?Scheduler $scheduler = null;

    protected function getNameSpace(): ?string
    {
        return $this->kernel->getSchedulerNamespace();
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

    protected function getScheduler() : ?Scheduler
    {
        return $this->scheduler ??= ContainerHelper::service(
            Scheduler::class,
            $this->kernel->getHttpKernel()->getContainer()
        );
    }

    protected function getManager(): ?ManagerInterface
    {
        return $this->getScheduler()
            ?->getManager()??parent::getManager();
    }

    protected function getContainer(): ?ContainerInterface
    {
        return $this->getScheduler()
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
        return 'schedulers';
    }

    protected function isProcessable(): bool
    {
        $processable = ! $this->kernel->getConfigError()
            && $this->getNameSpace()
            && $this->getDirectory()
            && $this->getScheduler();
        if ($processable) {
            $canBeProcess = $this
                ->getManager()
                ->dispatch('kernel.schedulerLoader', true);
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
            || ! ($scheduler = $this->getScheduler())
        ) {
            return;
        }
        $realPath = $splFileInfo->getRealPath();
        $manager = $this->getManager();
        // @dispatch(kernel.beforeRegisterScheduler)
        $manager?->dispatch(
            'kernel.beforeRegisterScheduler',
            $realPath,
            $scheduler,
            $this->kernel
        );
        $result = null;
        try {
            $className = $this->getClasNameFromFile($splFileInfo);
            if (!$className) {
                // @dispatch(kernel.registerScheduler)
                $manager?->dispatch(
                    'kernel.registerScheduler',
                    $realPath,
                    $scheduler,
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
                    && $ref->isSubclassOf(Task::class)
                    && $ref->getFileName() === $splFileInfo->getRealPath()
                ) {
                    $result = new $className($scheduler);
                    $scheduler->add($result);
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
            // @dispatch(kernel.registerScheduler)
            $manager?->dispatch(
                'kernel.registerScheduler',
                $realPath,
                $scheduler,
                $this->kernel,
                $result
            );
        } finally {
            if ($result) {
                $this->injectDependency($result);
            }
            // @dispatch(kernel.afterRegisterScheduler)
            $manager?->dispatch(
                'kernel.afterRegisterScheduler',
                $realPath,
                $scheduler,
                $this->kernel,
                $result
            );
        }
    }
}
