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

class MiddlewareGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $middlewareDir = null;
    private string $middlewareNamespace;

    public function __construct(string $name = null)
    {
        $middlewareNamespace = Decorator::kernel()?->getMiddlewareNamespace();
        if (!$middlewareNamespace) {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $middlewareNamespace = "$appNameSpace\\Middlewares\\";
        }
        $this->middlewareNamespace = $middlewareNamespace;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $namespace = rtrim($this->middlewareNamespace, '\\');
        $this
            ->setName('app:generate:middleware')
            ->setAliases(['generate-middleware'])
            ->setDescription(
                $this->translate('Generate middleware class.')
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

Middleware will use prefix namespace with %s

EOT),
                    '<info>%command.name%</info>',
                    'middleware',
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
        $middlewareName = preg_replace('~[\\\_]+~', '_', $name);
        $middlewareName = preg_replace_callback('~(_[a-zA-Z0-9]|[A-Z0-9])~', static function ($e) {
            return "_".trim($e[1], '_');
        }, $middlewareName);
        $middlewareName = strtolower(trim($middlewareName, '_'));
        return $name !== '' && Consolidation::isValidClassName($name)
            ? [
                'identity' => $middlewareName,
                'className' => $name,
            ]
            : null;
    }

    private ?array $middlewareList = null;

    private function isFileExists(string $className) : bool
    {
        $className = trim($className, '\\');
        $lowerClassName = strtolower($className);
        if ($this->middlewareList === null) {
            $this->middlewareList = [];
            $middlewareDirectory = $this->middlewareDir;
            $lengthStart = strlen($middlewareDirectory) + 1;
            foreach (Finder::create()
                         ->in($middlewareDirectory)
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
                $this->middlewareList[strtolower($class)] = $baseClassName;
            }
        }
        return isset($this->middlewareList[$lowerClassName]);
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
            $this->translate('Please enter middleware class name'),
            null,
            function ($name) {
                $definitions = $this->filterNames($name);
                if ($definitions === null) {
                    throw new InteractiveArgumentException(
                        $this->translate('Please enter valid middleware class name!')
                    );
                }
                $className = $definitions['className'];
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Middleware [%s] is invalid! class name contain reserved keyword!'
                            ),
                            $className
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate('Middleware [%s] exist'),
                            $this->middlewareNamespace . $className
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
        $middlewareDir = $path?->get('middleware');
        if (is_string($middlewareDir) && is_dir($middlewareDir)) {
            $this->middlewareDir = realpath($middlewareDir)??$middlewareDir;
        }

        if (!$this->middlewareDir) {
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Could not detect middleware directory'
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

        $fileName = $this->middlewareDir
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $named['className'])
            . '.php';
        if ($input->getOption('print')) {
            $output->writeln(
                $this->generateMiddlewareContent($named['className'])
            );
            return self::SUCCESS;
        }
        $output->writeln(
            sprintf(
                $this->translate(
                    'Middleware Name  : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $named['className']
                )
            )
        );

        $className = $this->middlewareNamespace . $named['className'];
        $output->writeln(
            sprintf(
                $this->translate(
                    'Middleware Class : %s'
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
                    'Middleware File  : %s'
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
                $this->generateMiddlewareContent(
                    $named['className']
                )
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translate(
                            '%s Could not save middleware!'
                        ),
                        '<fg=red;options=bold>[X]</>'
                    )
                );
                return self::SUCCESS;
            }
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Middleware successfully created!'
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

    private function generateMiddlewareContent(
        string $className
    ): string {
        $classes   = explode('\\', $className);
        $baseClassName = array_pop($classes);
        $namespace = trim($this->middlewareNamespace, '\\');
        if (!empty($classes)) {
            $namespace .= '\\' . implode('\\', $classes);
        }
        $time = date('c');
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use ArrayAccess\TrayDigita\Middleware\AbstractMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Autogenerated middleware : $className
 * @date $time
 */
class $baseClassName extends AbstractMiddleware
{
   /**
    * The middleware priorities.
    * Higher priority will call first 
    */
    protected int \$priority = self::DEFAULT_PRIORITY;

    protected function doProcess(ServerRequestInterface \$request): ServerRequestInterface|ResponseInterface
    {
        // do process middleware
        return \$request;
    }
}

PHP;
    }
}
