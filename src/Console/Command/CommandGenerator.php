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

class CommandGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $commandDirectory = null;
    private string $commandNamespace;

    protected function configure() : void
    {
        $kernel = ContainerHelper::use(
            KernelInterface::class,
            $this->getContainer()
        );
        if ($kernel instanceof BaseKernel) {
            $this->commandNamespace = $kernel->getCommandNameSpace();
            $this->commandDirectory = $kernel->getRegisteredDirectories()[$this->commandNamespace]??null;
        } else {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $this->commandNamespace = "$appNameSpace\\Commands\\";
        }

        $namespace = rtrim($this->commandNamespace, '\\');
        $this
            ->setName('app:generate:command')
            ->setAliases(['generate-command'])
            ->setDescription(
                $this->translateContext('Generate command class.', 'console')
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
                        . "Command will use prefix namespace with %s\n"
                        . "Command only support single class name.\n",
                        'console'
                    ),
                    '<info>%command.name%</info>',
                    'command',
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
        $commandName = preg_replace('~[\\\_]+~', '_', $name);
        $commandName = preg_replace_callback('~(_[a-zA-Z0-9]|[A-Z0-9])~', static function ($e) {
            return "_".trim($e[1], '_');
        }, $commandName);
        $commandName = strtolower(trim($commandName, '_'));
        return $name !== '' && Consolidation::isValidClassName($name)
            ? [
                'identity' => $commandName,
                'className' => $name,
            ]
            : null;
    }

    private ?array $commandList = null;

    private function isFileExists(string $className) : bool
    {
        $className = trim($className, '\\');
        $lowerClassName = strtolower($className);
        if ($this->commandList === null) {
            $this->commandList = [];
            $commandDirectory = $this->commandDirectory;
            if (!is_dir($commandDirectory)) {
                return false;
            }
            $lengthStart = strlen($commandDirectory) + 1;
            foreach (Finder::create()
                         ->in($commandDirectory)
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
                $this->commandList[strtolower($class)] = $baseClassName;
            }
        }
        return isset($this->commandList[$lowerClassName]);
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
            $this->translateContext('Please enter command class name', 'console'),
            null,
            function ($name) {
                $definitions = $this->filterNames($name);
                if ($definitions === null) {
                    throw new InteractiveArgumentException(
                        $this->translateContext(
                            'Please enter valid command class name!',
                            'console'
                        )
                    );
                }
                $commandName = $definitions['identity'];
                $className = $definitions['className'];
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Command [%s] is invalid! class name contain reserved keyword!',
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
                                'Command [%s] is invalid! Command only contain single class name, not namespaced!',
                                'console'
                            ),
                            $className
                        )
                    );
                }
                if (strlen($commandName) > 128) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Command [%s] is too long! Must be less or equal 128 characters',
                                'console'
                            ),
                            $commandName
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext('Command [%s] exist', 'console'),
                            $this->commandNamespace . $className
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
        if (!$this->commandDirectory) {
            $config = ContainerHelper::getNull(Config::class, $container) ?? new Config();
            $path = $config->get('path');
            $path = $path instanceof Config ? $path : null;
            $commandDirectory = $path?->get('command');
            if (is_string($commandDirectory) && is_dir($commandDirectory)) {
                $this->commandDirectory = realpath($commandDirectory) ?? $commandDirectory;
            }
        }

        if (!$this->commandDirectory) {
            $output->writeln(
                sprintf(
                    $this->translateContext(
                        '%s Could not detect command directory',
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

        $fileName = $this->commandDirectory
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $named['className'])
            . '.php';

        if ($input->getOption('print')) {
            $output->writeln(
                $this->generateCommandContent($named['className'])
            );
            return self::SUCCESS;
        }
        $name = 'app:console:'. strtolower($named['className']);
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'Command Name  : %s',
                    'console'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $name
                )
            )
        );
        $className = $this->commandNamespace . $named['className'];
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'Command Class : %s',
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
                    'Command File  : %s',
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
                $this->generateCommandContent(
                    $named['className']
                )
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translateContext(
                            '%s Could not save command!',
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
                        '%s Command successfully created!',
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

    private function generateCommandContent(
        string $className
    ): string {
        $classes   = explode('\\', $className);
        $baseClassName = array_pop($classes);
        $namespace = trim($this->commandNamespace, '\\');
        if (!empty($classes)) {
            $namespace .= '\\' . implode('\\', $classes);
        }
        $name = strtolower($className);
        $time = date('c');
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use ArrayAccess\TrayDigita\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function sprintf;

/**
 * Autogenerated command : $className
 * @date $time
 */
class $baseClassName extends AbstractCommand
{
    protected ?string \$name = 'app:console:$name';

    protected ?string \$description = 'Command <info>app:console:$name</info>';

    /**
     * Do configure the current command
     * @return void
     */
    protected function configure() : void
    {
        // do configure
        /*
        \$this->setDefinition([
            new InputArgument(
                name: 'name',
                mode: InputArgument::REQUIRED,
                description: 'Example Description'
            )
        ]);*/
    }

    protected function beforeConstruct(): void
    {
        // do before construct
    }

    /**
     * Do execute the current command
     * @see execute()
     * @param InputInterface \$input
     * @param OutputInterface \$output
     * @return int
     */
    protected function doExecute(InputInterface \$input, OutputInterface \$output): int
    {
        \$output->writeln(
            sprintf(
                'Console <info>%s</info> is ready!',
                \$this->getName()
            )
        );
        return self::SUCCESS;
    }
}

PHP;
    }
}
