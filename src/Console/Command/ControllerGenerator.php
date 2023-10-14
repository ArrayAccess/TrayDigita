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

class ControllerGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $controllerDir = null;
    private string $controllerNameSpace;

    public function __construct(string $name = null)
    {
        $controllerNameSpace = Decorator::kernel()?->getControllerNameSpace();
        if (!$controllerNameSpace) {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $controllerNameSpace = "$appNameSpace\\Controllers\\";
        }
        $this->controllerNameSpace = $controllerNameSpace;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $namespace = rtrim($this->controllerNameSpace, '\\');
        $this
            ->setName('app:generate:controller')
            ->setAliases(['generate-controller'])
            ->setDescription(
                $this->translate(
                    'Generate controller class.'
                )
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
The %s help you to create controller object.

Controller will use prefix namespace with %s 

Character slash %s, hyphen %s and underscore %s
Will converted into namespace separator
EOT),
                    '<info>%command.name%</info>',
                    sprintf('<comment>%s</comment>', $namespace),
                    '[ <comment>/</comment> ]',
                    '[ <comment>-</comment> ]',
                    '[ <comment>_</comment> ]'
                )
            );
    }

    protected function filterName(string $name) : ?string
    {
        $name = trim($name);
        $name = ltrim(str_replace(['/', '_', '-'], '\\', $name), '\\');
        $name = preg_replace('~\\\+~', '\\', $name);
        $name = ucwords($name, '\\');
        $name = preg_replace_callback('~_([a-z])~', function ($e) {
            return ucwords($e[1]);
        }, $name);
        $name = trim($name, '_');
        return $name !== '' && Consolidation::isValidClassName($name)
            ? $name
            : null;
    }

    private ?array $controllerList = null;

    private function isFileExists(string $className) : bool
    {
        $className = trim($className, '\\');
        $lowerClassName = strtolower($className);
        if ($this->controllerList === null) {
            $this->controllerList = [];
            $controllerDirectory = $this->controllerDir;
            $lengthStart = strlen($controllerDirectory) + 1;
            foreach (Finder::create()
                         ->in($controllerDirectory)
                         // depth <= 10
                         ->depth('<= 10')
                         ->ignoreVCS(true)
                         ->ignoreDotFiles(true)
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
                $this->controllerList[strtolower($class)] = $baseClassName;
            }
        }
        return isset($this->controllerList[$lowerClassName]);
    }

    protected function askClassName(InputInterface $input, OutputInterface $output) : ?string
    {
        $io = new SymfonyStyle($input, $output);
        return $io->ask(
            $this->translate('Please enter controller name'),
            null,
            function ($name) {
                $className = $this->filterName($name);
                if ($className === null) {
                    throw new InteractiveArgumentException(
                        $this->translate('Please enter valid controller name!')
                    );
                }
                if (count(explode('\\', $className)) > 5) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Controller [%s] is too long! Must be less or equal 5 namespaces'
                            ),
                            $className
                        )
                    );
                }
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Controller [%s] is invalid! class name contain reserved keyword!'
                            ),
                            $className
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate('Controller [%s] exist'),
                            $this->controllerNameSpace . $className
                        )
                    );
                }
                return $className;
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
        $config = ContainerHelper::getNull(Config::class, $container)??new Config();
        $path = $config->get('path');
        $path = $path instanceof Config ? $path : null;
        $controllerDir = $path?->get('controller');
        if (is_string($controllerDir) && is_dir($controllerDir)) {
            $this->controllerDir = realpath($controllerDir)??$controllerDir;
        }

        if (!$this->controllerDir) {
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Could not detect controller directory'
                    ),
                    '<fg=red;options=bold>[X]</>'
                )
            );
            return self::FAILURE;
        }

        $name = $this->askClassName($input, $output);
        if (!$name && !$input->isInteractive()) {
            $output->writeln(
                sprintf(
                    $this->translate('%s generator only support in interactive mode'),
                    '<fg=red;options=bold>[X]</>'
                )
            );
            return self::FAILURE;
        }

        $fileName = $this->controllerDir
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $name)
            . '.php';

        if ($input->getOption('print')) {
            $output->writeln(
                $this->generateControllerContent($name)
            );
            return self::SUCCESS;
        }
        $output->writeln(
            sprintf(
                $this->translate('Controller Name  : %s'),
                sprintf(
                    '<comment>%s</comment>',
                    $name
                ),
            )
        );
        $className = $this->controllerNameSpace . $name;
        $output->writeln(
            sprintf(
                $this->translate(
                    'Controller Class : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $className
                )
            )
        );
        $output->writeln(
            sprintf(
                $this->translate('Controller File  : %s'),
                sprintf(
                    '<comment>%s</comment>',
                    $fileName
                )
            )
        );
        /** @noinspection DuplicatedCode */
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
                        $this->translate(
                            'Please enter valid answer! (Yes / No)'
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
                $this->generateControllerContent($name)
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translate(
                            '%s Could not save controller!'
                        ),
                        '<fg=red;options=bold>[X]</>'
                    )
                );
                return self::SUCCESS;
            }
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Controller successfully created!'
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

    private function generateControllerContent(string $className): string
    {
        $classes   = explode('\\', $className);
        $baseClassName = array_pop($classes);
        $namespace = trim($this->controllerNameSpace, '\\');
        $prefix = '/';
        if (!empty($classes)) {
            $namespace .= '\\' . implode('\\', $classes);
            $prefix = '/'.strtolower(implode('/', $classes));
        }
        $time = date('c');
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use ArrayAccess\TrayDigita\Routing\AbstractController;
use ArrayAccess\TrayDigita\Routing\Attributes\Get;
use ArrayAccess\TrayDigita\Routing\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Autogenerated controller : $className
 * @date $time
 */
#[Group('$prefix')] // route group prefix
class $baseClassName extends AbstractController
{
    /**
     * Do routing for get($prefix?)
     *
     * @param ServerRequestInterface \$request
     * @param ResponseInterface \$response
     * @param array<string> \$parameters
     * @param string \$prefixSlash
     * @param string \$suffixSlash
     * @return ResponseInterface
     */
    #[Get('/')]
    public function main(
        ServerRequestInterface \$request,
        ResponseInterface \$response,
        array \$parameters,
        string \$prefixSlash,
        string \$suffixSlash
    ) : ResponseInterface {
        // do job task with response
        return \$response;
    }
}

PHP;
    }
}
