<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use ArrayAccess\TrayDigita\Database\Seeders;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;
use function class_exists;
use function is_bool;
use function is_dir;

class KernelSeederLoader extends AbstractLoaderNameBased
{
    private ?Seeders $seeder = null;

    protected function getNameSpace(): ?string
    {
        return $this->kernel->getSeederNamespace();
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

    protected function getSeeder() : ?Seeders
    {
        return $this->seeder ??= ContainerHelper::service(
            Seeders::class,
            $this->kernel->getHttpKernel()->getContainer()
        );
    }

    protected function getManager(): ?ManagerInterface
    {
        return $this->getSeeder()
            ?->getManager()??parent::getManager();
    }

    protected function getContainer(): ?ContainerInterface
    {
        return $this->getSeeder()
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
        return 'seeders';
    }

    protected function isProcessable(): bool
    {
        $processable = ! $this->kernel->getConfigError()
            && $this->getNameSpace()
            && $this->getDirectory()
            && $this->getSeeder();
        if ($processable) {
            $canBeProcess = $this
                ->getManager()
                ->dispatch('kernel.seederLoader', true);
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
            || ! ($seeder = $this->getSeeder())
        ) {
            return;
        }
        $realPath = $splFileInfo->getRealPath();
        $manager = $this->getManager();
        // @dispatch(kernel.beforeRegisterSeeder)
        $manager?->dispatch(
            'kernel.beforeRegisterSeeder',
            $realPath,
            $seeder,
            $this->kernel
        );
        $result = null;
        try {
            $className = $this->getClasNameFromFile($splFileInfo);
            if (!$className) {
                // @dispatch(kernel.registerSeeder)
                $manager?->dispatch(
                    'kernel.registerSeeder',
                    $realPath,
                    $seeder,
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
                    && $ref->isSubclassOf(FixtureInterface::class)
                    && $ref->getFileName() === $splFileInfo->getRealPath()
                ) {
                    $result = new $className();
                    $seeder->addFixture($result);
                }
            } catch (Throwable $e) {
                $this->getLogger()?->debug(
                    $e,
                    [
                        'loaderMode' => 'seeder',
                        'classLoader' => $this::class,
                        'className' => $className,
                        'file' => $splFileInfo->getRealPath()
                    ]
                );
            }
            // @dispatch(kernel.registerSeeder)
            $manager?->dispatch(
                'kernel.registerSeeder',
                $realPath,
                $seeder,
                $this->kernel,
                $this,
                $result
            );
        } finally {
            if ($result) {
                $this->injectDependency($result);
            }
            // @dispatch(kernel.afterRegisterSeeder)
            $manager?->dispatch(
                'kernel.afterRegisterSeeder',
                $realPath,
                $seeder,
                $this->kernel,
                $this,
                $result
            );
        }
    }
}
