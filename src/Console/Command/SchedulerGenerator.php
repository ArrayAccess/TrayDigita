<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InteractiveArgumentException;
use ArrayAccess\TrayDigita\HttpKernel\BaseKernel;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
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

class SchedulerGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $schedulerDirectory = null;

    private string $schedulerNamespace;

    protected function configure() : void
    {
        $kernel = ContainerHelper::use(
            KernelInterface::class,
            $this->getContainer()
        );
        if ($kernel instanceof BaseKernel) {
            $this->schedulerNamespace = $kernel->getSchedulerNamespace();
            $this->schedulerDirectory = $kernel->getRegisteredDirectories()[$this->schedulerNamespace]??null;
        } else {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $this->schedulerNamespace = "$appNameSpace\\Schedulers\\";
        }

        $namespace = rtrim($this->schedulerNamespace, '\\');
        $this
            ->setName('app:generate:scheduler')
            ->setAliases(['generate-scheduler'])
            ->setDescription(
                $this->translateContext('Generate scheduler class.', 'console')
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
                        . "Scheduler will use prefix namespace with %s\n"
                        . "Scheduler only support single class name.\n\n",
                        'console'
                    ),
                    '<info>%command.name%</info>',
                    'scheduler',
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
        $schedulerName = preg_replace('~[\\\_]+~', '_', $name);
        $schedulerName = preg_replace_callback('~(_[a-zA-Z0-9]|[A-Z0-9])~', static function ($e) {
            return "_".trim($e[1], '_');
        }, $schedulerName);
        $schedulerName = strtolower(trim($schedulerName, '_'));
        return $name !== '' && Consolidation::isValidClassName($name)
            ? [
                'identity' => $schedulerName,
                'className' => $name,
            ]
            : null;
    }

    private ?array $schedulerList = null;

    private function isFileExists(string $className) : bool
    {
        $className = trim($className, '\\');
        $lowerClassName = strtolower($className);
        if ($this->schedulerList === null) {
            $this->schedulerList = [];
            $schedulerDirectory = $this->schedulerDirectory;
            $lengthStart = strlen($schedulerDirectory) + 1;
            foreach (Finder::create()
                         ->in($schedulerDirectory)
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
                $this->schedulerList[strtolower($class)] = $baseClassName;
            }
        }
        return isset($this->schedulerList[$lowerClassName]);
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
            $this->translateContext('Please enter scheduler class name', 'console'),
            null,
            function ($name) {
                $definitions = $this->filterNames($name);
                if ($definitions === null) {
                    throw new InteractiveArgumentException(
                        $this->translateContext(
                            'Please enter valid scheduler class name!',
                            'console'
                        )
                    );
                }
                $schedulerName = $definitions['identity'];
                $className = $definitions['className'];
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Scheduler [%s] is invalid! class name contain reserved keyword!',
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
                                'Scheduler [%s] is invalid! Scheduler only contain single class name, not namespaced!',
                                'console'
                            ),
                            $className
                        )
                    );
                }
                if (strlen($schedulerName) > 128) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Scheduler [%s] is too long! Must be less or equal 128 characters',
                                'console'
                            ),
                            $schedulerName
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Scheduler [%s] exist',
                                'console'
                            ),
                            $this->schedulerNamespace . $className
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
        if (!$this->schedulerDirectory) {
            $config = ContainerHelper::use(Config::class, $container)
                ?? new Config();
            $path = $config->get('path');
            $path = $path instanceof Config ? $path : null;
            $schedulerDirectory = $path?->get('scheduler');
            if (is_string($schedulerDirectory) && is_dir($schedulerDirectory)) {
                $this->schedulerDirectory = realpath($schedulerDirectory) ?? $schedulerDirectory;
            }
        }

        if (!$this->schedulerDirectory) {
            $output->writeln(
                sprintf(
                    $this->translateContext(
                        '%s Could not detect scheduler directory',
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

        $fileName = $this->schedulerDirectory
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $named['className'])
            . '.php';

        $name = preg_replace_callback('~([A-Z])~', function ($a) {
            return " $a[1]";
        }, $named['className']);
        $name = ucwords($name);
        $name = 'Scheduler '.trim($name);
        if ($input->getOption('print')) {
            $output->writeln(
                $this->generateSchedulerContent($name, $named['identity'], $named['className'])
            );
            return self::SUCCESS;
        }
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'Scheduler Name     : %s',
                    'console'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $name
                )
            )
        );
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'Scheduler Identity : %s',
                    'console'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $named['identity']
                )
            )
        );
        $className = $this->schedulerNamespace . $named['className'];
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'Scheduler Class : %s',
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
                    'Scheduler File  : %s',
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
                $this->generateSchedulerContent(
                    $name,
                    $named['identity'],
                    $named['className']
                )
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translateContext(
                            '%s Could not save scheduler!',
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
                        '%s Scheduler successfully created!',
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

    private function generateSchedulerContent(
        string $name,
        string $identity,
        string $className
    ): string {
        $classes   = explode('\\', $className);
        $baseClassName = array_pop($classes);
        $namespace = trim($this->schedulerNamespace, '\\');
        if (!empty($classes)) {
            $namespace .= '\\' . implode('\\', $classes);
        }
        $time = date('c');
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use ArrayAccess\TrayDigita\Scheduler\Messages\Failure;
use ArrayAccess\TrayDigita\Scheduler\Messages\Success;
use ArrayAccess\TrayDigita\Scheduler\Messages\Skipped;
use ArrayAccess\TrayDigita\Scheduler\Messages\Unknown;
use ArrayAccess\TrayDigita\Scheduler\Interfaces\SchedulerTimeInterface;
use ArrayAccess\TrayDigita\Scheduler\Runner;

/**
 * Autogenerated scheduler : $className
 * @date $time
 */
class $baseClassName extends Task
{
    /**
     * Name of scheduler.
     * @var string \$name
     */
    protected string \$name = '$name';

    /**
     * Unique scheduler identity.
     * Should use lowercase alphanumeric characters & underscore only
     * @see getIdentity()
     * @var string \$identity
     */
    protected string \$identity = '$identity';

    /**
     * This property for default interval if getInterval method is default from task
     * @see getInterval()
     * @var int|SchedulerTimeInterface \$interval
     */
    protected int|SchedulerTimeInterface \$interval = 60;

    /**
     * Scheduler periodic
     * @return int|SchedulerTimeInterface
     */
    public function getInterval(): int|SchedulerTimeInterface
    {
        // change with integer in seconds or periodic object
        return \$this->interval;
    }

    /**
     * Method to trigger scheduler process
     * Returning status / message can be :
     * @use Success
     * @use Skipped mean the process skipped
     * @use Failure mean the process is failure
     * @use Unknown -> do not use this
     * @param Runner \$runner
     * @return MessageInterface
     */
    public function start(Runner \$runner): MessageInterface
    {
        // do process
        // return new Success('Stringable Message');
        return new Skipped('Just Skipped Scheduler');
    }
}

PHP;
    }
}
