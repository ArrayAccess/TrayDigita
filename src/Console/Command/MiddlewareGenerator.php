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

class MiddlewareGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $middlewareDirectory = null;

    private string $middlewareNamespace;

    protected function configure() : void
    {
        $kernel = ContainerHelper::use(
            KernelInterface::class,
            $this->getContainer()
        );
        if ($kernel instanceof BaseKernel) {
            $this->middlewareNamespace = $kernel->getMiddlewareNamespace();
            $this->middlewareDirectory = $kernel->getRegisteredDirectories()[$this->middlewareNamespace]??null;
        } else {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $this->middlewareNamespace = "$appNameSpace\\Middlewares\\";
        }
        $namespace = rtrim($this->middlewareNamespace, '\\');
        $this
            ->setName('app:generate:middleware')
            ->setAliases(['generate-middleware'])
            ->setDescription(
                $this->translateContext('Generate middleware class.', 'console')
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
                        . "Middleware will use prefix namespace with %s\n",
                        'console'
                    ),
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
        /** @noinspection DuplicatedCode */
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
            $middlewareDirectory = $this->middlewareDirectory;
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
            $this->translateContext(
                'Please enter middleware class name',
                'console'
            ),
            null,
            function ($name) {
                $definitions = is_string($name) ? $this->filterNames($name) : null;
                if ($definitions === null) {
                    throw new InteractiveArgumentException(
                        $this->translateContext(
                            'Please enter valid middleware class name!',
                            'console'
                        )
                    );
                }
                $className = $definitions['className'];
                if (count(explode('\\', $className)) > 1) {
                    $message = $this->translateContext(
                        'Middleware [%s] is invalid! Middleware only contain single class name, not namespaced!',
                        'console'
                    );
                    throw new InteractiveArgumentException(
                        sprintf(
                            $message,
                            $className
                        )
                    );
                }
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Middleware [%s] is invalid! class name contain reserved keyword!',
                                'console'
                            ),
                            $className
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translateContext(
                                'Middleware [%s] exist',
                                'console'
                            ),
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
        if (!$this->middlewareDirectory) {
            $config = ContainerHelper::use(Config::class, $container) ?? new Config();
            $path = $config->get('path');
            $path = $path instanceof Config ? $path : null;
            $middlewareDirectory = $path?->get('middleware');
            if (is_string($middlewareDirectory) && is_dir($middlewareDirectory)) {
                $this->middlewareDirectory = realpath($middlewareDirectory) ?? $middlewareDirectory;
            }
        }

        if (!$this->middlewareDirectory) {
            $output->writeln(
                sprintf(
                    $this->translateContext(
                        '%s Could not detect middleware directory',
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

        $fileName = $this->middlewareDirectory
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
                $this->translateContext(
                    'Middleware Name  : %s',
                    'console'
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
                $this->translateContext(
                    'Middleware Class : %s',
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
                    'Middleware File  : %s',
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
            $this->translateContext(
                'Are you sure to continue (Yes/No)?',
                'console'
            ),
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
                $this->generateMiddlewareContent(
                    $named['className']
                )
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translateContext(
                            '%s Could not save middleware!',
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
                        '%s Middleware successfully created!',
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
