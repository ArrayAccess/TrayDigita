<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InteractiveArgumentException;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use function array_pop;
use function date;
use function dirname;
use function explode;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_string;
use function ltrim;
use function mkdir;
use function preg_replace;
use function preg_replace_callback;
use function realpath;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function ucwords;
use const DIRECTORY_SEPARATOR;

class DatabaseEventGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $databaseEventDir = null;
    private string $databaseEventNamespace;

    public function __construct(string $name = null)
    {
        $databaseEventNamespace = Decorator::kernel()?->getDatabaseEventNamespace();
        if (!$databaseEventNamespace) {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $databaseEventNamespace = "$appNameSpace\\DatabaseEvents\\";
        }
        $this->databaseEventNamespace = $databaseEventNamespace;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $namespace = rtrim($this->databaseEventNamespace, '\\');
        $this
            ->setName('app:generate:database-event')
            ->setAliases([
                'generate-database-event',
                //'generate-db-event',
                //'app:generate:db-event'
            ])
            ->setDescription(
                $this->translateContext('Generate database event class.', 'console')
            )->setDefinition([
                new InputOption(
                    'print',
                    'p',
                    InputOption::VALUE_NONE,
                    $this->translateContext('Print generated class file only', 'console')
                )
            ])->setHelp(
                sprintf(
                    $this->translateContext(
                        "The %s help you to create %s object.\n\n"
                            . "Database Event will use prefix namespace with %s\n"
                            . "Database Event only support single class name.",
                        'console'
                    ),
                    '<info>%command.name%</info>',
                    'databaseEvent',
                    sprintf(
                        '<comment>%s</comment>',
                        $namespace
                    )
                )
            );
    }

    /**
     * @param string $name
     * @return ?array{name: string, className:string}
     */
    protected function filterNames(string $name) : ?array
    {
        /** @noinspection DuplicatedCode */
        $name = trim($name);
        $name = ltrim(str_replace(['/', '_', '-'], '\\', $name), '\\');
        $name = preg_replace('~\\\+~', '\\', $name);
        $name = ucwords($name, '\\');
        $name = preg_replace_callback('~_([a-z])~', static function ($e) {
            return ucwords($e[1]);
        }, $name);
        $name = trim($name, '_');
        $databaseEventName = preg_replace('~[\\\_]+~', '_', $name);
        $databaseEventName = preg_replace_callback('~(_[a-zA-Z0-9]|[A-Z0-9])~', static function ($e) {
            return "_".trim($e[1], '_');
        }, $databaseEventName);
        $databaseEventName = strtolower(trim($databaseEventName, '_'));
        return $name !== '' && Consolidation::isValidClassName($name)
            ? [
                'identity' => $databaseEventName,
                'className' => $name,
            ]
            : null;
    }

    private ?array $databaseEventList = null;

    private function isFileExists(string $className) : bool
    {
        $className = trim($className, '\\');
        $lowerClassName = strtolower($className);
        if ($this->databaseEventList === null) {
            $this->databaseEventList = [];
            $databaseEventDirectory = $this->databaseEventDir;
            $lengthStart = strlen($databaseEventDirectory) + 1;
            foreach (Finder::create()
                         ->in($databaseEventDirectory)
                         ->ignoreVCS(true)
                         ->ignoreDotFiles(true)
                         // depth <= 10
                         ->depth('<= 10')
                         ->name('/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
                         ->files() as $file
            ) {
                $realPath = $file->getRealPath();
                $baseClassName = substr(
                    $realPath,
                    $lengthStart,
                    -4
                );
                $class = str_replace('/', '\\', $baseClassName);
                $this->databaseEventList[strtolower($class)] = $baseClassName;
            }
        }
        return isset($this->databaseEventList[$lowerClassName]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return ?array{name: string, className:string}
     */
    protected function askClassName(InputInterface $input, OutputInterface $output) : ?array
    {
        $io = new SymfonyStyle($input, $output);
        return $io->ask(
            $this->translateContext('Please enter database event class name', 'console'),
            null,
            function ($name) {
                $definitions = $this->filterNames($name);
                if ($definitions === null) {
                    throw new InteractiveArgumentException(
                        $this->translateContext('Please enter valid database event class name!', 'console')
                    );
                }
                $databaseEventName = $definitions['identity'];
                $className = $definitions['className'];
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Event [%s] is invalid! class name contain reserved keyword!',
                                'console'
                            ),
                            $className
                        )
                    );
                }
                if (count(explode('\\', $className)) > 1) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Event [%s] is invalid! Database event only contain single class name, not namespaced!',
                                'console'
                            ),
                            $className
                        )
                    );
                }
                if (strlen($databaseEventName) > 128) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Database event [%s] is too long! Must be less or equal 128 characters',
                                'console'
                            ),
                            $databaseEventName
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext('Database event [%s] exist', 'console'),
                            $this->databaseEventNamespace . $className
                        )
                    );
                }
                return $definitions;
            }
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($output->isQuiet()) {
            return self::INVALID;
        }

        $input->setInteractive(true);
        $container = $this->getContainer();
        $config = ContainerHelper::use(Config::class, $container)??new Config();
        $path = $config->get('path');
        $path = $path instanceof Config ? $path : null;
        $databaseEventDir = $path?->get('databaseEvent');
        if (is_string($databaseEventDir) && is_dir($databaseEventDir)) {
            $this->databaseEventDir = realpath($databaseEventDir)??$databaseEventDir;
        }

        if (!$this->databaseEventDir) {
            $output->writeln(
                sprintf(
                    $this->translateContext(
                        '%s Could not detect database event directory',
                        'console'
                    ),
                    '<fg=red;options=bold>[X]</>'
                )
            );
            return self::FAILURE;
        }

        $named = $this->askClassName($input, $output);
        if (!$named && !$input->isInteractive()) {
            $output->writeln(
                sprintf(
                    $this->translateContext(
                        '%s generator only support in interactive mode',
                        'console'
                    ),
                    '<fg=red;options=bold>[X]</>'
                )
            );
            return self::FAILURE;
        }

        $fileName = $this->databaseEventDir
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $named['className'])
            . '.php';

        if ($input->getOption('print')) {
            $output->writeln(
                $this->generateDatabaseEventContent($named['className'])
            );
            return self::SUCCESS;
        }
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'Database Event Name : %s',
                    'console'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $named['className']
                )
            )
        );
        $className = $this->databaseEventNamespace . $named['className'];
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'DatabaseEvent Class : %s',
                    'console'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $className
                )
            )
        );
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'DatabaseEvent File  : %s',
                    'console'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $fileName
                )
            )
        );
        /** @noinspection DuplicatedCode */
        $io = new SymfonyStyle($input, $output);
        $answer = !$input->isInteractive() || $io->ask(
            $this->translateContext('Are you sure to continue (Yes/No)?', 'console'),
            null,
            function ($e) {
                $e = !is_string($e) ? '' : $e;
                $e = strtolower(trim($e));
                $ask = match ($e) {
                    'yes' => true,
                    'no' => false,
                    default => null
                };
                if ($ask === null) {
                    throw new InteractiveArgumentException(
                        $this->translateContext(
                            'Please enter valid answer! (Yes / No)',
                            'console'
                        )
                    );
                }
                return $ask;
            }
        );
        if ($answer) {
            if (!is_dir(dirname($fileName))) {
                @mkdir(dirname($fileName), 0755, true);
            }
            $status = @file_put_contents(
                $fileName,
                $this->generateDatabaseEventContent(
                    $named['className']
                )
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translateContext(
                            '%s Could not save databaseEvent!',
                            'console'
                        ),
                        '<fg=red;options=bold>[X]</>'
                    )
                );
                return self::SUCCESS;
            }
            $output->writeln(
                sprintf(
                    $this->translateContext(
                        '%s DatabaseEvent successfully created!',
                        'console'
                    ),
                    '<fg=green;options=bold>[√]</>'
                )
            );
            return self::FAILURE;
        }
        $output->writeln(
            sprintf(
                '<comment>%s</comment>',
                $this->translateContext('Operation cancelled!', 'console')
            )
        );
        return self::SUCCESS;
    }

    private function generateDatabaseEventContent(
        string $className
    ): string {
        $classes   = explode('\\', $className);
        $baseClassName = array_pop($classes);
        $namespace = trim($this->databaseEventNamespace, '\\');
        if (!empty($classes)) {
            $namespace .= '\\' . implode('\\', $classes);
        }
        $time = date('c');
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use ArrayAccess\TrayDigita\Database\Attributes\SubscribeEvent;
use ArrayAccess\TrayDigita\Database\DatabaseEvent;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Mapping\PrePersist;

/**
 * Autogenerated databaseEvent : $className
 * @date $time
 * @link https://www.doctrine-project.org/projects/doctrine-orm/en/2.16/reference/events.html#events-overview
 */
// using @SubscribeEvent to load by method name
// or call the mapping event to use different way
#[SubscribeEvent]
// #[PostLoad]
class $baseClassName extends DatabaseEvent
{
    /**
     * like @use PostLoad
     * @param PostLoadEventArgs \$eventArgs
     */
    public function postLoad(PostLoadEventArgs \$eventArgs)
    {
        // do with post event
    }
    
    #[PrePersist]
    public function doPrePersistsProgress(PrePersistEventArgs \$eventArgs)
    {
        // do event
    }
}

PHP;
    }
}
