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

class ModuleGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $moduleDir = null;
    private string $moduleNamespace;

    public function __construct(string $name = null)
    {
        $moduleNamespace = Decorator::kernel()?->getModuleNamespace();
        if (!$moduleNamespace) {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $moduleNamespace = "$appNameSpace\\Modules\\";
        }
        $this->moduleNamespace = $moduleNamespace;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $namespace = rtrim($this->moduleNamespace, '\\');
        $this
            ->setName('app:generate:module')
            ->setAliases(['generate-module'])
            ->setDescription(
                $this->translate('Generate module class.')
            )->setDefinition([
                new InputOption(
                    'print',
                    'p',
                    InputOption::VALUE_NONE,
                    $this->translate('Print generated class file only')
                )
            ])->setHelp(
                sprintf(
                    $this->translate(<<<EOT
The %s help you to create %s object.

Module will use prefix namespace with %s
Module only support single class name.

EOT),
                    '<info>%command.name%</info>',
                    'module',
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
        $name = trim($name);
        $name = ltrim(str_replace(['/', '_', '-'], '\\', $name), '\\');
        $name = preg_replace('~\\\+~', '\\', $name);
        $name = ucwords($name, '\\');
        $name = preg_replace_callback('~_([a-z])~', static function ($e) {
            return ucwords($e[1]);
        }, $name);
        $name = trim($name, '_');
        $moduleName = preg_replace('~[\\\_]+~', '_', $name);
        $moduleName = preg_replace_callback('~(_[a-zA-Z0-9]|[A-Z0-9])~', static function ($e) {
            return "_".trim($e[1], '_');
        }, $moduleName);
        $moduleName = strtolower(trim($moduleName, '_'));
        return $name !== '' && Consolidation::isValidClassName($name)
            ? [
                'identity' => $moduleName,
                'className' => $name,
            ]
            : null;
    }

    private ?array $moduleList = null;

    private function isFileExists(string $className) : bool
    {
        $className = trim($className, '\\');
        $lowerClassName = strtolower($className);
        if ($this->moduleList === null) {
            $this->moduleList = [];
            $moduleDirectory = $this->moduleDir;
            $lengthStart = strlen($moduleDirectory) + 1;
            foreach (Finder::create()
                         ->in($moduleDirectory)
                         ->ignoreVCS(true)
                         ->ignoreDotFiles(true)
                         // depth <= 10
                         ->depth('<= 10')
                         ->name('/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
                         ->directories() as $file
            ) {
                $realPath = $file->getRealPath();
                $baseClassName = substr(
                    $realPath,
                    $lengthStart,
                    -4
                );
                $class = str_replace('/', '\\', $baseClassName);
                $this->moduleList[strtolower($class)] = $baseClassName;
            }
        }
        return isset($this->moduleList[$lowerClassName]);
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
            $this->translate('Please enter module class name'),
            null,
            function ($name) {
                $definitions = $this->filterNames($name);
                if ($definitions === null) {
                    throw new InteractiveArgumentException(
                        $this->translate('Please enter valid module class name!')
                    );
                }
                $moduleName = $definitions['identity'];
                $className = $definitions['className'];
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Module [%s] is invalid! class name contain reserved keyword!'
                            ),
                            $className
                        )
                    );
                }
                if (count(explode('\\', $className)) > 1) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Module [%s] is invalid! Module only contain single class name, not namespaced!'
                            ),
                            $className
                        )
                    );
                }
                if (strlen($moduleName) > 128) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Module [%s] is too long! Must be less or equal 128 characters'
                            ),
                            $moduleName
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate('Module [%s] exist'),
                            $this->moduleNamespace . $className
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
        $config = $container?->has(Config::class)
            ? $container->get(Config::class)
            : null;
        $config = $config instanceof Config ? $config : new Config();
        $path = $config->get('path');
        $path = $path instanceof Config ? $path : null;
        $moduleDir = $path?->get('module');
        if (is_string($moduleDir) && is_dir($moduleDir)) {
            $this->moduleDir = realpath($moduleDir)??$moduleDir;
        }

        if (!$this->moduleDir) {
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Could not detect module directory'
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
                    $this->translate(
                        '%s generator only support in interactive mode'
                    ),
                    '<fg=red;options=bold>[X]</>'
                )
            );
            return self::FAILURE;
        }

        $fileName = $this->moduleDir
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $named['className'])
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $named['className'])
            . '.php';

        if ($input->getOption('print')) {
            $output->writeln(
                $this->generateModuleContent($named['className'])
            );
            return self::SUCCESS;
        }
        $output->writeln(
            sprintf(
                $this->translate(
                    'Module Name  : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $named['className']
                )
            )
        );
        $namespace = $this->moduleNamespace . $named['className'];
        $className = $namespace . '\\' . $named['className'];
        $output->writeln(
            sprintf(
                $this->translate(
                    'Module Class : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $className
                )
            )
        );
        $output->writeln(
            sprintf(
                $this->translate(
                    'Module Namespace : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $namespace
                )
            )
        );
        $output->writeln(
            sprintf(
                $this->translate(
                    'Module File  : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $fileName
                )
            )
        );
        $io = new SymfonyStyle($input, $output);
        $answer = !$input->isInteractive() || $io->ask(
            $this->translate('Are you sure to continue (Yes/No)?'),
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
                        $this->translate('Please enter valid answer! (Yes / No)')
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
                $this->generateModuleContent(
                    $named['className']
                )
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translate(
                            '%s Could not save module!'
                        ),
                        '<fg=red;options=bold>[X]</>'
                    )
                );
                return self::SUCCESS;
            }
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Module successfully created!'
                    ),
                    '<fg=green;options=bold>[√]</>'
                )
            );
            return self::FAILURE;
        }
        $output->writeln(
            sprintf(
                '<comment>%s</comment>',
                $this->translate('Operation cancelled!')
            )
        );
        return self::SUCCESS;
    }

    private function generateModuleContent(
        string $className
    ): string {
        $classes   = explode('\\', $className);
        $baseClassName = array_pop($classes);
        $namespace = trim($this->moduleNamespace, '\\');
        if (!empty($classes)) {
            $namespace .= '\\' . implode('\\', $classes);
        }
        $time = date('c');
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace\\$className;

use ArrayAccess\TrayDigita\HttpKernel\BaseKernel;
use ArrayAccess\TrayDigita\Module\AbstractModule;

/**
 * Autogenerated module : $className
 * @date $time
 */
class $baseClassName extends AbstractModule
{
    protected int \$priority = self::DEFAULT_PRIORITY;

    private bool \$didInit = false;

    /**
     * @method doInit() called when kernel loaded init
     * @see BaseKernel::init() 
     * @return void
     */
    protected function doInit(): void
    {
        if (\$this->didInit) {
            return;
        }
        \$this->didInit = true;
        // do any module instance
    }
}

PHP;
    }
}
