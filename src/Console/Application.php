<?php
/**
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpUndefinedNamespaceInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Console\Command\ApplicationCheck;
use ArrayAccess\TrayDigita\Console\Command\BuiltInWebServer;
use ArrayAccess\TrayDigita\Console\Command\ChecksumGenerator;
use ArrayAccess\TrayDigita\Console\Command\CommandGenerator;
use ArrayAccess\TrayDigita\Console\Command\ControllerGenerator;
use ArrayAccess\TrayDigita\Console\Command\DatabaseChecker;
use ArrayAccess\TrayDigita\Console\Command\DatabaseEventGenerator;
use ArrayAccess\TrayDigita\Console\Command\EntityGenerator;
use ArrayAccess\TrayDigita\Console\Command\MiddlewareGenerator;
use ArrayAccess\TrayDigita\Console\Command\ModuleGenerator;
use ArrayAccess\TrayDigita\Console\Command\SchedulerAction;
use ArrayAccess\TrayDigita\Console\Command\SchedulerGenerator;
use ArrayAccess\TrayDigita\Console\Command\SeederGenerator;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\Runtime\UnProcessableException;
use ArrayAccess\TrayDigita\HttpKernel\BaseKernel;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Kernel\Kernel;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as SymfonyConsole;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function class_exists;
use function debug_backtrace;
use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function str_replace;
use function trim;
use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const DIRECTORY_SEPARATOR;
use const TD_APP_DIRECTORY;

class Application extends SymfonyConsole implements
    ContainerIndicateInterface,
    ManagerAllocatorInterface
{
    use ManagerAllocatorTrait;

    private array $factoryCommands = [
        ApplicationCheck::class,
        DatabaseChecker::class,
        BuiltInWebServer::class,
        ChecksumGenerator::class,
        ControllerGenerator::class,
        EntityGenerator::class,
        SchedulerGenerator::class,
        SeederGenerator::class,
        MiddlewareGenerator::class,
        CommandGenerator::class,
        DatabaseEventGenerator::class,
        ModuleGenerator::class,
        SchedulerAction::class,
    ];

    private ?array $errors = null;

    /**
     * @var array<class-string<Command>, true>
     */
    private array $registered = [];

    /**
     * @var array<class-string<Command>, Command>
     */
    private array $queue = [];

    /**
     * @var array<string, true>
     */
    private array $defaultSymfonyCommands = [
        HelpCommand::class => true,
        ListCommand::class => true,
        CompleteCommand::class => true,
        DumpCompletionCommand::class => true
    ];

    /** @noinspection PhpMissingFieldTypeInspection */
    private $migrationConfig = null;

    public function __construct(
        protected ContainerInterface $container,
        string $appName = '',
        string $appVersion = ''
    ) {
        $manager = ContainerHelper::use(ManagerInterface::class, $this->container);
        if ($manager) {
            $this->setManager($manager);
            $originalName = $appName ?: Kernel::NAME;
            $originalVersion = $appVersion ?: Kernel::VERSION;
            $appName = $manager->dispatch('appName', $originalName) ?: $appName;
            $appName = is_string($appName) ? $appName : $originalName;
            $appVersion = $manager->dispatch('appVersion', $originalVersion) ?: $appVersion;
            $appVersion = is_string($appVersion) ? $appVersion : $originalVersion;
        }

        parent::__construct($appName, $appVersion);
    }

    private function configureDBCommands(): void
    {
        if (is_array($this->errors)) {
            return;
        }
        // called outside
        if ((debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['class']??null) !== __CLASS__) {
            return;
        }

        $this->errors = [];
        try {
            $database = ContainerHelper::use(Connection::class, $this->container);
            if (!$database) {
                throw new UnProcessableException(
                    'Database connection is not ready!'
                );
            }
            $entityProvider = new SingleManagerProvider($database->getEntityManager());
            ConsoleRunner::addCommands($this, $entityProvider);
            $kernel = ContainerHelper::use(KernelInterface::class, $this->container);
            if ($kernel instanceof BaseKernel) {
                $migrationNamespace = $kernel->getMigrationNameSpace();
                $migrationPath = $kernel->getRegisteredDirectories()[$migrationNamespace] ??null;
            } else {
                $namespace = dirname(
                    str_replace(
                        '\\',
                        '/',
                        __NAMESPACE__
                    )
                );
                $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
                $migrationNamespace = "$appNameSpace\\Migrations\\";
            }
            if (empty($migrationPath)) {
                $config = ContainerHelper::use(Config::class, $this->container)
                    ->get('path');
                if ($config instanceof Config) {
                    $migrationPath = $config->get('migration')??null;
                } else {
                    $migrationPath = TD_APP_DIRECTORY . DIRECTORY_SEPARATOR . 'Migrations';
                }
            }

            if (class_exists('Doctrine\Migrations\Configuration\Migration\ConfigurationArray')
                && class_exists('Doctrine\Migrations\DependencyFactory')
                && class_exists('Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager')
                && class_exists('Doctrine\Migrations\Tools\Console\ConsoleRunner')
            ) {
                try {
                    $migrationNamespace = trim($migrationNamespace, '\\');
                    $this->migrationConfig = new \Doctrine\Migrations\Configuration\Migration\ConfigurationArray([
                        'migrations_paths' => [
                            $migrationNamespace => $migrationPath
                        ]
                    ]);
                    $factory = \Doctrine\Migrations\DependencyFactory::fromEntityManager(
                        $this->migrationConfig,
                        new \Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager(
                            $database->getEntityManager()
                        ),
                        ContainerHelper::use(LoggerInterface::class, $this->container)
                    );
                    \Doctrine\Migrations\Tools\Console\ConsoleRunner::addCommands($this, $factory);
                } catch (Throwable) {
                    // skipped
                }
            }
        } catch (Throwable $e) {
            $this->errors[Connection::class] = $e;
        }
    }

    private function configureTheCommands(): void
    {
        // called outside
        if ((debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['class']??null) !== __CLASS__) {
            return;
        }
        $manager = $this->getManager();
        // @dispatch(console.beforeConfigureCommands)
        $manager?->dispatch('console.beforeConfigureCommands', $this);
        try {
            $this->configureDBCommands();
            foreach ($this->factoryCommands as $command) {
                try {
                    if (isset($this->registered[$command])) {
                        continue;
                    }
                    $className = $command;
                    $command = new $command;
                    parent::add($this->appendDefaultContainer($command));
                    $this->registered[$className] = true;
                } catch (Throwable $e) {
                    $this->errors[$command] = $e;
                }
            }
            foreach ($this->queue as $commandName => $command) {
                unset($this->queue[$commandName]);
                if (isset($this->registered[$commandName])) {
                    continue;
                }
                parent::add($this->appendDefaultContainer($command));
            }
            // @dispatch(console.configureCommands)
            $manager?->dispatch('console.configureCommands', $this);
        } finally {
            // @dispatch(console.afterConfigureCommands)
            $manager?->dispatch('console.afterConfigureCommands', $this);
        }
    }

    private function appendDefaultContainer(Command $command): Command
    {
        $manager = $this->getManager();
        if ($command instanceof ContainerAllocatorInterface
            && !$command->getContainer()
        ) {
            $command->setContainer($this->getContainer());
        }

        if ($manager
            && $command instanceof ManagerAllocatorInterface
            && !$command->getManager()
        ) {
            $command->setManager($manager);
        }
        return $command;
    }

    /**
     * @inheritdoc
     * @uses doRun() to override run method
     */
    final public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        $this->configureTheCommands();
        return parent::run($input, $output);
    }

    /**
     * @return ?\Doctrine\Migrations\Configuration\Migration\ConfigurationLoader
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getMigrationConfig()
    {
        return $this->migrationConfig;
    }

    final public function add(Command $command): ?Command
    {
        // direct
        if (isset($this->defaultSymfonyCommands[$command::class])) {
            return parent::add($command);
        }
        if (in_array($command::class, $this->factoryCommands)) {
            return null;
        }
        $this->queue[$command::class] = $command;
        return $command;
    }

    /**
     * @return ?array<string, Throwable>
     * @noinspection PhpUnused
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this);
    }
}
