<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\ApplicationChecker;

use ArrayAccess\TrayDigita\Cache\Cache;
use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function is_string;
use function sprintf;

class CacheChecker extends AbstractChecker
{
    use WriterHelperTrait;

    // SERVICES

    public function check(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->applicationCheck->getContainer();
        if (!$container?->has(CacheItemPoolInterface::class)) {
            $this->writeDanger(
                $output,
                $this->translateContext(
                    'Can not get cache object from container',
                    'console'
                )
            );
            return Command::FAILURE;
        }
        try {
            $cache = $container->get(CacheItemPoolInterface::class);
            if (!$cache instanceof CacheItemPoolInterface) {
                $this->writeDanger(
                    $output,
                    $this->translateContext(
                        'Cache is not valid object from container',
                        'console'
                    ),
                );
                return Command::FAILURE;
            }
        } catch (Throwable $error) {
            $this->writeDanger(
                $output,
                $this->translateContext('Cache object error', 'console')
            );
            $this->writeIndent(
                $output,
                sprintf(
                    '<comment>%s</comment> [<comment>%s</comment>] <fg=red>%s</>',
                    $this->translateContext('Error:', 'console'),
                    $error::class,
                    $error->getMessage()?:$this->translateContext(
                        'Unknown Error',
                        'console'
                    )
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
            $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }
        $this->write(
            $output,
            sprintf(
                $this->translateContext(
                    'Cache object is set [%s]',
                    'console'
                ),
                sprintf(
                    '<info>%s</info>',
                    $cache::class
                )
            ),
            true
        );
        if (!$cache instanceof Cache) {
            return Command::FAILURE;
        }
        try {
            $adapter = $cache->getAdapter();
        } catch (Throwable $error) {
            $this->writeIndent(
                $output,
                sprintf(
                    '<comment>%s</comment> [<comment>%s</comment>] <fg=red>%s</>',
                    $this->translateContext('Error:', 'console'),
                    $error::class,
                    $error->getMessage()?:$this->translateContext(
                        'Unknown Error',
                        'console'
                    )
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
            $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        $this->writeIndent(
            $output,
            sprintf(
                '<info>- %s</info> [%s]',
                $this->translateContext('Adapter', 'console'),
                $adapter::class
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );
        $this->writeIndent(
            $output,
            sprintf(
                '<info>- %s</info> (%s)',
                $this->translateContext('Default Lifetime', 'console'),
                $cache->getDefaultLifetime()
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );
        $config = ContainerHelper::use(Config::class, $container)
            ?->get('cache');
        if (!$config instanceof Config) {
            return Command::SUCCESS;
        }
        $namespace = $config->get('namespace');
        if (is_string($namespace)) {
            $this->writeIndent(
                $output,
                sprintf(
                    '<info>- %s</info> [%s]',
                    $this->translateContext('Namespace', 'console'),
                    $namespace
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
        return Command::SUCCESS;
    }
}
