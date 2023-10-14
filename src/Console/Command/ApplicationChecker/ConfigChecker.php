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
use const DIRECTORY_SEPARATOR;

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
        $config = $container?->get(Config::class)->get('environment');
        if (!$config instanceof Config) {
            $this->writeDanger(
                $output,
                $this->translate('Config environment is not set')
            );
            return Command::FAILURE;
        }
        $displayError = $config->get('displayErrorDetails') === true;
        $profiling = $config->get('profiling') === true;
        $debugBar = $profiling && $config->get('debugBar') === true;
        $this->write(
            $output,
            sprintf(
                $this->translate('Debugging - error display [%s]'),
                $displayError
                    ? sprintf('<fg=red>%s</>', $this->translate('Enabled'))
                    : sprintf('<info>%s</info>', $this->translate('Disabled'))
            ),
            $displayError ? self::MODE_WARNING : self::MODE_SUCCESS
        );

        $this->write(
            $output,
            sprintf(
                $this->translate('Benchmark debug bar [%s]'),
                $debugBar
                    ? sprintf('<fg=red>%s</>', $this->translate('Enabled'))
                    : sprintf('<info>%s</info>', $this->translate('Disabled'))
            ),
            $debugBar ? self::MODE_WARNING : self::MODE_SUCCESS
        );

        $this->write(
            $output,
            sprintf(
                $this->translate('Benchmark profiling [%s]'),
                $profiling
                    ? sprintf('<fg=red>%s</>', $this->translate('Enabled'))
                    : sprintf('<info>%s</info>', $this->translate('Disabled'))
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
                        $this->translate('Benchmarks records (%d)'),
                        $count
                    )
                )
            );
            foreach ($groupList as $groupName => $total) {
                $this->writeIndent(
                    $output,
                    sprintf(
                        '<info>%s</info> [<comment>%s</comment>] (%d)',
                        $this->translate('Benchmarks group'),
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
        $config = $container->has(Config::class)
            ? $container->get(Config::class)
            : null;
        $config = $config instanceof Config ? $config : new Config();
        $path = $config->get('path');
        $path = $path instanceof Config ? $path : null;
        $directory = defined('TD_APP_DIRECTORY')
            && is_string(\TD_APP_DIRECTORY)
            && is_dir(\TD_APP_DIRECTORY)
            ? \TD_APP_DIRECTORY
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
                $this->translate('App directory is not exists'),
                false
            );
        }
        $this->write(
            $output,
            $this->translate('Required Applications directory'),
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
                        $this->translate('Storage directory is not writable')
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
                    $this->translate('directory'),
                    $directory
                )
            );
        }

        return $containFail ? Command::INVALID : Command::SUCCESS;
    }

    protected function validateConfig(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->applicationCheck->getContainer();
        $kernel = $container->has('kernel') ? $container->get('kernel') : null;
        $kernel = $kernel instanceof KernelInterface
            ? $kernel
            : Decorator::kernel();
        if (!$container && ($container = $kernel->getContainer())) {
            $this->applicationCheck->setContainer($container);
        }
        $configFile = $kernel->getConfigFile()?:(
            !defined('CONFIG_FILE') ? \CONFIG_FILE : null
        );
        if (!is_string($configFile)
            || !file_exists($configFile)
            || $kernel->getConfigError()
        ) {
            $configFile  = !is_string($configFile) ? '' : $configFile;
            $problem = $kernel->getConfigError();
            $message = match ($problem) {
                KernelInterface::CONFIG_NOT_FILE => sprintf(
                    $this->translate('Configuration file is not a valid file (%s)'),
                    '<fg=red>NOT_FILE</>'
                ),
                KernelInterface::CONFIG_NOT_ITERABLE => sprintf(
                    $this->translate('Configuration file is invalid (%s)'),
                    '<fg=red>NOT_ITERABLE</>'
                ),
                KernelInterface::CONFIG_EMPTY_FILE => sprintf(
                    $this->translate('Configuration file is empty (%s)'),
                    '<fg=red>EMPTY_FILE</>'
                ),
                default => sprintf(
                    $this->translate('Configuration file does not exists (%s)'),
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
                    $this->translate('Configuration'),
                    $configFile
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
            return Command::FAILURE;
        }
        $config = $container->get(Config::class);
        $config = $config instanceof Config
            ? $config
            : null;
        if (!$config) {
            $this->writeDanger(
                $output,
                $this->translate('Configuration file is not exists on container')
            );
            return Command::FAILURE;
        }
        $databaseConfig = $config->get('database');
        $databaseConfig = $databaseConfig instanceof Config ? $databaseConfig : null;
        if (!$databaseConfig) {
            $this->writeDanger(
                $output,
                $this->translate(
                    'Configuration file does not contain database configuration'
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
                $this->translate(
                    'Configuration file does not contain valid database configuration'
                )
            );
            return Command::FAILURE;
        }
        $security = $config->get('security');
        $security = $security instanceof Config ? $security : null;
        if (!$security) {
            $this->writeDanger(
                $output,
                $this->translate(
                    'Configuration file does not contain security configuration'
                )
            );
            return Command::FAILURE;
        }

        if (!$security->get('secret') || !$security->get('salt')) {
            $this->writeDanger(
                $output,
                $this->translate(
                    'Configuration file does not contain valid security configuration'
                )
            );
            return Command::FAILURE;
        }

        $this->writeSuccess(
            $output,
            $this->translate('Configuration file is exist & valid')
        );
        $this->writeIndent(
            $output,
            sprintf(
                '<fg=green;options=bold>[√]</> %s [<comment>%s</comment>]',
                $this->translate('Configuration'),
                $configFile
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );
        return Command::SUCCESS;
    }
}
