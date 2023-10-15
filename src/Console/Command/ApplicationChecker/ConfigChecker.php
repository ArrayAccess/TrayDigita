<?php
/** @noinspection PhpUnusedParameterInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\ApplicationChecker;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function defined;
use function file_exists;
use function is_dir;
use function is_string;
use function is_writable;
use function realpath;
use function sprintf;
use function ucfirst;
use const CONFIG_FILE;
use const DIRECTORY_SEPARATOR;
use const TD_APP_DIRECTORY;

class ConfigChecker extends AbstractChecker
{
    use WriterHelperTrait;

    protected array $defaultPaths = [
        'scheduler' => 'Schedulers',
        'controller' => 'Controllers',
        'entity' => 'Entities',
        'language' => 'Languages',
        'middleware' =>'Middlewares',
        'migration' => 'Migrations',
        'module' => 'Modules',
        'view' => 'Views',
        'databaseEvent' => 'DatabaseEvents',
        'storage' =>  'storage',
        'data' => 'data',
    ];

    public function check(InputInterface $input, OutputInterface $output): int
    {
        $this->validateConfig($input, $output);
        $this->validateEnvironment($input, $output);
        $this->validatePath($input, $output);
        return Command::SUCCESS;
    }

    protected function validateEnvironment(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->applicationCheck->getContainer();
        $config = ContainerHelper::use(Config::class, $container)?->get('environment');
        if (!$config instanceof Config) {
            $this->writeDanger(
                $output,
                $this->translateContext('Config environment is not set', 'console')
            );
            return Command::FAILURE;
        }
        $displayError = $config->get('displayErrorDetails') === true;
        $profiling = $config->get('profiling') === true;
        $debugBar = $profiling && $config->get('debugBar') === true;
        $this->write(
            $output,
            sprintf(
                $this->translateContext('Debugging - error display [%s]', 'console'),
                $displayError
                    ? sprintf('<fg=red>%s</>', $this->translateContext('Enabled', 'console'))
                    : sprintf('<info>%s</info>', $this->translateContext('Disabled', 'console'))
            ),
            $displayError ? self::MODE_WARNING : self::MODE_SUCCESS
        );

        $this->write(
            $output,
            sprintf(
                $this->translateContext('Benchmark debug bar [%s]', 'console'),
                $debugBar
                    ? sprintf('<fg=red>%s</>', $this->translateContext('Enabled', 'console'))
                    : sprintf('<info>%s</info>', $this->translateContext('Disabled', 'console'))
            ),
            $debugBar ? self::MODE_WARNING : self::MODE_SUCCESS
        );

        $this->write(
            $output,
            sprintf(
                $this->translateContext('Benchmark profiling [%s]', 'console'),
                $profiling
                    ? sprintf('<fg=red>%s</>', $this->translateContext('Enabled', 'console'))
                    : sprintf('<info>%s</info>', $this->translateContext('Disabled', 'console'))
            ),
            $profiling ? self::MODE_WARNING : self::MODE_SUCCESS
        );

        $profiler = ContainerHelper::getNull(
            ProfilerInterface::class,
            $container
        );
        if ($profiler && $output->isVerbose()) {
            $count = 0;
            $groupList = [];
            foreach ($profiler->getGroups() as $group) {
                $name = $group->getName();
                $groupList[$name] ??= 0;
                $groupList[$name] += count($group->getAllRecords());
                $count += $groupList[$name];
            }
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>%s</info>',
                    sprintf(
                        $this->translateContext('Benchmarks records (%d)', 'console'),
                        $count
                    )
                )
            );
            foreach ($groupList as $groupName => $total) {
                $this->writeIndent(
                    $output,
                    sprintf(
                        '<info>%s</info> [<comment>%s</comment>] (%d)',
                        $this->translateContext('Benchmarks group', 'console'),
                        $groupName,
                        $total
                    ),
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
            }
        }

        return Command::SUCCESS;
    }

    protected function validatePath(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->applicationCheck->getContainer();
        $config = ContainerHelper::use(Config::class, $container)??new Config();
        $path = $config->get('path');
        $path = $path instanceof Config ? $path : null;
        $directory = defined('TD_APP_DIRECTORY')
            && is_string(TD_APP_DIRECTORY)
            && is_dir(TD_APP_DIRECTORY)
            ? TD_APP_DIRECTORY
            : null;
        $root = ContainerHelper::use(KernelInterface::class)
            ->getRootDirectory();
        if (!$path || !$directory) {
            $path = new Config();
            $config->set('path', $path);
            if (!$directory) {
                $directory = $root . '/app';
            }
            if ($directory) {
                foreach ($this->defaultPaths as $name => $value) {
                    $dir = $directory;
                    if ($name === 'data' || $name === 'storage') {
                        $dir = $root;
                    }
                    $file = $dir . DIRECTORY_SEPARATOR . $value;
                    $file = realpath($file) ?: $file;
                    $path->set($name, $file);
                }
            }
        }

        $data = [];
        $containFail = false;
        foreach ($this->defaultPaths as $name => $dir) {
            $file = $path->get($name);
            $status = is_string($file) && is_dir($file);
            $data[$name] = [
                'valid' => $status,
                'directory' => $file
            ];
            if (!$data[$name]['valid']) {
                $containFail = true;
            }
        }
        $isVerbose = $output->isVerbose();
        if (!$directory) {
            $this->write(
                $output,
                $this->translateContext('App directory is not exists', 'console'),
                false
            );
        }
        $this->write(
            $output,
            $this->translateContext('Required Applications directory', 'console'),
            !$containFail
        );

        foreach ($data as $name => $datum) {
            $directory = $datum['directory'];
            $additionalComment = ';';
            if ($datum['valid'] && $isVerbose && $name === 'storage') {
                $datum['valid'] = is_writable($directory);
                if (!$datum['valid']) {
                    $additionalComment = sprintf(
                        ' <fg=red>%s</>',
                        $this->translateContext('Storage directory is not writable', 'console')
                    );
                }
            }
            $status = $datum['valid']
                ? '<fg=green;options=bold>[√]</>'
                : '<fg=red;options=bold>[X]</>';
            $directory = $isVerbose ? " [<comment>$directory</comment>]$additionalComment" : '';
            $name = ucfirst($name);
            $this->writeIndent(
                $output,
                sprintf(
                    "%s %s %s%s",
                    $status,
                    $name,
                    $this->translateContext('directory', 'console'),
                    $directory
                )
            );
        }

        return $containFail ? Command::INVALID : Command::SUCCESS;
    }

    protected function validateConfig(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->applicationCheck->getContainer();
        $kernel = ContainerHelper::use(KernelInterface::class, $container)??Decorator::kernel();
        if (!$container && ($container = $kernel->getContainer())) {
            $this->applicationCheck->setContainer($container);
        }
        $configFile = $kernel->getConfigFile()?:(
            !defined('CONFIG_FILE') ? CONFIG_FILE : null
        );
        if (!is_string($configFile)
            || !file_exists($configFile)
            || $kernel->getConfigError()
        ) {
            $configFile  = !is_string($configFile) ? '' : $configFile;
            $problem = $kernel->getConfigError();
            $message = match ($problem) {
                KernelInterface::CONFIG_NOT_FILE => sprintf(
                    $this->translateContext('Configuration file is not a valid file (%s)', 'console'),
                    '<fg=red>NOT_FILE</>'
                ),
                KernelInterface::CONFIG_NOT_ITERABLE => sprintf(
                    $this->translateContext('Configuration file is invalid (%s)', 'console'),
                    '<fg=red>NOT_ITERABLE</>'
                ),
                KernelInterface::CONFIG_EMPTY_FILE => sprintf(
                    $this->translateContext('Configuration file is empty (%s)', 'console'),
                    '<fg=red>EMPTY_FILE</>'
                ),
                default => sprintf(
                    $this->translateContext('Configuration file does not exists (%s)', 'console'),
                    '<fg=red>UNAVAILABLE</>'
                )
            };
            $this->writeDanger(
                $output,
                $message
            );
            $this->writeIndent(
                $output,
                sprintf(
                    '<fg=red;options=bold>[X]</> %s [<comment>%s</comment>]',
                    $this->translateContext('Configuration', 'console'),
                    $configFile
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
            return Command::FAILURE;
        }

        $config = ContainerHelper::getNull(Config::class, $container);
        if (!$config) {
            $this->writeDanger(
                $output,
                $this->translateContext('Configuration file is not exists on container', 'console')
            );
            return Command::FAILURE;
        }
        $databaseConfig = $config->get('database');
        $databaseConfig = $databaseConfig instanceof Config ? $databaseConfig : null;
        if (!$databaseConfig) {
            $this->writeDanger(
                $output,
                $this->translateContext(
                    'Configuration file does not contain database configuration',
                    'console'
                )
            );
            return Command::FAILURE;
        }
        if (!$databaseConfig->get('user')
            && !$databaseConfig->get('dbuser')
            && !$databaseConfig->get('password')
            && !$databaseConfig->get('dbpass')
            && !$databaseConfig->get('dbpassword')
            && !$databaseConfig->get('dbname')
            && !$databaseConfig->get('name')
        ) {
            $this->writeDanger(
                $output,
                $this->translateContext(
                    'Configuration file does not contain valid database configuration',
                    'console'
                )
            );
            return Command::FAILURE;
        }
        $security = $config->get('security');
        $security = $security instanceof Config ? $security : null;
        if (!$security) {
            $this->writeDanger(
                $output,
                $this->translateContext(
                    'Configuration file does not contain security configuration',
                    'console'
                )
            );
            return Command::FAILURE;
        }

        if (!$security->get('secret') || !$security->get('salt')) {
            $this->writeDanger(
                $output,
                $this->translateContext(
                    'Configuration file does not contain valid security configuration',
                    'console'
                )
            );
            return Command::FAILURE;
        }

        $this->writeSuccess(
            $output,
            $this->translateContext(
                'Configuration file is exist & valid',
                'console'
            )
        );
        $this->writeIndent(
            $output,
            sprintf(
                '<fg=green;options=bold>[√]</> %s [<comment>%s</comment>]',
                $this->translateContext('Configuration', 'console'),
                $configFile
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );
        return Command::SUCCESS;
    }
}
