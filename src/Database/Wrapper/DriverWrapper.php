<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use ArrayAccess\TrayDigita\Util\Filter\Conversion;
use DateTimeZone;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DoctrineConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use SensitiveParameter;
use Throwable;
use function is_string;
use function preg_match;
use function trim;

final class DriverWrapper extends AbstractDriverMiddleware implements ManagerIndicateInterface
{
    use CallStackTraceTrait,
        ManagerDispatcherTrait;

    public function __construct(
        private readonly Connection $databaseConnection,
        private readonly Driver $wrappedDriver
    ) {
        parent::__construct($wrappedDriver);
    }

    protected function getPrefixNameEventIdentity(): ?string
    {
        return 'connection';
    }

    public function getDatabaseConnection(): Connection
    {
        return $this->databaseConnection;
    }

    public function getManager(): ?ManagerInterface
    {
        return $this->getDatabaseConnection()->getManager();
    }

    public function connect(#[SensitiveParameter] array $params) : DoctrineConnection
    {
        $this->assertCallstack();

        // @dispatch(connection.beforeConnect)
        $this->dispatchBefore($params, $this->getDatabaseConnection());
        try {
            $connection = new ConnectionWrapper(
                $this->getDatabaseConnection(),
                parent::connect($params)
            );

            $this->initConnection($connection);
            // @dispatch(connection.connect)
            $this->dispatchCurrent(
                $params,
                $this->getDatabaseConnection(),
                $this,
                $connection
            );
            return $connection;
        } finally {
            $this->resetCallstack();
            // @dispatch(connection.afterConnect)
            $this->dispatchAfter(
                $params,
                $this->getDatabaseConnection(),
                $this,
                $connection??null
            );
        }
    }

    private function initConnection(Driver\Connection $connection): void
    {
        // @dispatch(connection.beforeInitConnection)
        $this->dispatchBefore($connection, $this->getDatabaseConnection());
        $platform = $this->wrappedDriver->getDatabasePlatform($connection);
        $query = '';
        $config = $this->getDatabaseConnection()->getDatabaseConfig();
        $charset = $config['charset'] ?? 'utf8';
        $timezone = $config['timezone'] ?? '+00:00';
        if ($timezone instanceof DateTimeZone) {
            $timezone = Conversion::convertDateTimeZoneToSQLTimezone($timezone);
        } elseif (is_string($timezone)) {
            preg_match(
                '~^([+-])?\s*([0-9]{2})\s*:\s*([0-9]{2})$~',
                $timezone,
                $match
            );
            if ($match) {
                $prefix = $match[1] ?: '+';
                $timezone = "$prefix$match[2]:$match[3]";
            } else {
                try {
                    $timezone = new DateTimeZone($timezone);
                    $timezone = Conversion::convertDateTimeZoneToSQLTimezone($timezone);
                } catch (Throwable) {
                    $timezone = '+00:00';
                }
            }
        } else {
            $timezone = '+00:00';
        }

        // set utc timezone
        if ($platform instanceof AbstractMySQLPlatform) {
            $charset = $config['charset'] ?? 'utf8mb4';
            $charset = !is_string($charset) || trim($charset) === '' ? 'utf8mb4' : $charset;
            $query = "SET ";
            if (!preg_match('~[^0-9a-z-_]~', $charset)) {
                $query .= "NAMES '$charset',";
            }
            //$query = "SET NAMES '$charset', CHARSET '$charset',";
            //$query .= " CHARACTER_SET_DATABASE = UTF8MB4,";
            //$query .= " CHARACTER_SET_SERVER = UTF8MB4,";
            //$query .= " CHARACTER_SET_RESULTS = UTF8MB4,";
            //$query .= " CHARACTER_SET_CONNECTION = UTF8MB4,";
            //$query .= " CHARACTER_SET_CLIENT = UTF8MB4,";
            $query .= " TIME_ZONE = '$timezone'";
            $query .= ";";
        } elseif ($platform instanceof PostgreSQLPlatform) {
            if (preg_match('~^(utf[0-9]+)[a-z]~i', $charset, $match)) {
                $charset = $match[1];
            }
            if (!preg_match('~[^0-9a-z_-]~', $charset)) {
                $query = "SET NAMES '$charset';";
            }
            $query .= "SET TIME ZONE '$timezone';";
        } elseif ($platform instanceof OraclePlatform) {
            $query = "ALTER DATABASE SET TIME_ZONE='$timezone';";
        } elseif ($platform instanceof DB2Platform) {
            $query = "SET SESSION TIME_ZONE='$timezone';";
        }
        try {
            if ($query) {
                $result = $connection->exec($query);
            }
            // @dispatch(connection.initConnection)
            $this->dispatchCurrent(
                $connection,
                $this->getDatabaseConnection(),
                $query,
                $result??null
            );
        } catch (Throwable) {
        } finally {
            // @dispatch(connection.afterInitConnection)
            $this->dispatchAfter(
                $connection,
                $this->getDatabaseConnection(),
                $query,
                $result??null
            );
        }
    }
}
