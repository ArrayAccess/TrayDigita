<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\ApplicationChecker;

use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Database\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function sprintf;

class DatabaseChecker extends AbstractChecker
{
    use WriterHelperTrait;

    protected ?EntityManager $entityManager = null;

    public function check(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->applicationCheck->getContainer();
        if (!$container?->has(Connection::class)) {
            $this->write(
                $output,
                'Can not get database object from container',
                false
            );
            return Command::FAILURE;
        }

        $database = $container->get(Connection::class);
        if (!$database instanceof Connection) {
            $this->write(
                $output,
                'Database connection is not valid object from container',
                false
            );
            return Command::FAILURE;
        }
        $platform = null;
        $error = null;
        $config = null;
        $ormConfig = null;
        $driverException = null;
        try {
            $config = $database->getDatabaseConfig();
            $ormConfig = $database->getEntityManager()->getConfiguration();
            $platform = $database->getDatabasePlatform()::class;
            $database->connect();
        } catch (DriverException $e) {
            $error = $e;
            $driverException = $e;
        } catch (Throwable $e) {
            $error = $e;
        }
        if ($error) {
            $this->write(
                $output,
                'Database connection error.',
                false
            );
            $this->writeIndent(
                $output,
                sprintf(
                    '<comment>Error:</comment> [<comment>%s</comment>] <fg=red>%s</>',
                    $error::class,
                    $error->getMessage()
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
            $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        } else {
            $this->write(
                $output,
                sprintf(
                    'Database connection succeed [<info>%s</info>]',
                    $database::class
                ),
                true
            );
        }

        if (!$error && !$this->entityManager) {
            $orm = clone $ormConfig;
            $cache = new ArrayAdapter();
            $orm->setMetadataCache($cache);
            $orm->setResultCache($cache);
            $orm->setHydrationCache($cache);
            $orm->setQueryCache($cache);
            $this->entityManager = new EntityManager(
                $database->getConnection(),
                $orm,
                $database->getEntityManager()->getEventManager()
            );
        }

        $dbName = $config?->get('dbname');
        $dbName = $dbName?:$config?->get('path');
        $driver = $config?->get('driver');
        $dbUser = $config?->get('user');
        $dbHost = $driver instanceof Driver\AbstractSQLiteDriver
            ? ($config?->get('path')?:(
            $config?->get('memory') ? ':memory:' : null
            )
            )
            : $config?->get('host');

        if ($dbName) {
            $this->writeIndent(
                $output,
                sprintf(
                    '%s <info>Database name</info> [<comment>%s</comment>]',
                    $error ? '<fg=red;options=bold>[X]</>' : '<fg=green;options=bold>[√]</>',
                    $dbName
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }

        if ($dbHost) {
            $errorX = (bool)$error;
            if ($driverException
                && $driver instanceof Driver\AbstractSQLiteDriver
                && $driverException->getCode() !== 1042
            ) {
                $errorX = false;
            }
            $errMsg = $errorX ? '<fg=red;options=bold>[X]</>' : '<fg=green;options=bold>[√]</>';
            $this->writeIndent(
                $output,
                sprintf(
                    '%s <info>Database host</info> [<comment>%s</comment>]',
                    $errMsg,
                    $dbHost
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
        if ($dbUser) {
            $this->writeIndent(
                $output,
                sprintf(
                    '%s <info>Database user</info> [<comment>%s</comment>]',
                    $error ? '<fg=red;options=bold>[X]</>' : '<fg=green;options=bold>[√]</>',
                    $dbUser
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }

        $this->writeIndent(
            $output,
            $driver instanceof Driver
                ? sprintf(
                    '<fg=green;options=bold>[√]</> <info>Driver</info> [<comment>%s</comment>]',
                    $driver::class
                )
                : '<fg=red;options=bold>[X]</> <info>Driver</info> [<comment>Unknown</comment>]',
            OutputInterface::VERBOSITY_VERBOSE
        );
        $this->writeIndent(
            $output,
            $platform
                ? sprintf(
                    '<fg=green;options=bold>[√]</> <info>Platform</info> [<comment>%s</comment>]',
                    $platform
                ) : '<fg=red;options=bold>[X]</> <info>Platform</info> [<comment>Unknown</comment>]',
            OutputInterface::VERBOSITY_VERBOSE
        );
        return Command::SUCCESS;
    }
}
