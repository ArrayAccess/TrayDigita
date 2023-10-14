<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Logger;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Logger\Handler\DatabaseHandler;
use DateTimeZone;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Redis;
use Stringable;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use function class_exists;
use function dirname;
use function extension_loaded;
use function is_callable;
use function is_dir;
use function is_int;
use function is_numeric;
use function is_string;
use function is_writable;
use function max;
use function strtolower;
use function trim;

/**
 * @mixin Monolog
 */
class Logger extends AbstractLogger implements ResettableInterface, ContainerIndicateInterface
{
    const DEFAULT_NAME = 'default';

    const DEFAULT_LEVEL = Level::Critical;

    protected ?Monolog $logger = null;

    private bool $enable = true;

    public function __construct(
        protected ContainerInterface $container
    ) {
    }

    public function isEnable(): bool
    {
        return $this->enable;
    }

    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    public function setLogger(Monolog $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): Monolog
    {
        if ($this->logger) {
            return $this->logger;
        }
        $this->logger = $this->configureLogger();
        return $this->logger;
    }

    protected function configureLogger() : Monolog
    {
        $config = $this->container->has(Config::class)
            ? $this->container->get(Config::class)
            : null;
        $config = $config instanceof Config ? $config->get('log') : null;
        if (!$config instanceof Config) {
            return new Monolog(
                self::DEFAULT_NAME
            );
        }

        $enabled = !($config->get('enable') === false);
        $this->setEnable($enabled);

        $name = $config->get('name');
        $timezone = $config->get('timezone');
        $timezone = $timezone instanceof DateTimeZone
            ? $timezone
            : null;
        $name = is_string($name) ? $name : self::DEFAULT_NAME;
        $logger = new Monolog(
            $name,
            timezone: $timezone
        );

        $adapter =  $config->get('adapter');
        $maxFiles = $config->get('maxFiles');
        $maxFiles = is_numeric($maxFiles) ? (int)$maxFiles : 5;
        $maxFiles = max($maxFiles, 1);

        $handlers = $config->get('handlers');
        $processors = $config->get('processor');
        $adapterOptions = $config->get('adapterOptions');
        $adapterOptions = $adapterOptions instanceof Config
            ? $adapterOptions->toArray()
            : [];

        $level = $config->get('level');
        $level = $level instanceof Level ? $level : self::DEFAULT_LEVEL;
        if (!$level instanceof Level) {
            $level = is_string($level)
                ? Level::fromName(strtolower($level))
                : (
                is_int($level)
                    ? Level::fromValue($level)
                    : self::DEFAULT_LEVEL
                );
        }
        try {
            if ($adapter instanceof HandlerInterface) {
                $logger->pushHandler($adapter);
                return $logger;
            }
            $adapter = is_string($adapter)
                ? $adapter
                : 'rotate';
            $adapter = match (strtolower(trim($adapter))) {
                'database' => DatabaseHandler::class,
                'rotate',
                'file',
                'stream' => RotatingFileHandler::class,
                'syslog' => SyslogHandler::class,
                'redis' => RedisHandler::class,
                default => $adapter
            };
            $file = $config->get('file');
            switch ($adapter) {
                case RedisHandler::class:
                    if (!extension_loaded('redis')
                        || class_exists(Redis::class)
                    ) {
                        return $logger;
                    }
                    try {
                        $port = $adapterOptions['port'] ?? null;
                        $port = !is_int($port) ? null : $port;
                        $host = $adapterOptions['host'] ?? null;
                        $host = !is_int($host) ? 6379 : $host;
                        $adapterOptions['port'] = $port;
                        $adapterOptions['host'] = $host??'127.0.0.1';
                        $name = $adapterOptions['name']??'log';
                        $name = !is_string($name) ? 'log' : $name;
                        $timeout = $adapterOptions['timeout']??0;
                        $timeout = !is_int($timeout) ? 0 : $timeout;
                        $redis = new Redis($adapterOptions);
                        if ($redis->connect($host, $port, $timeout)) {
                            $logger->pushHandler(
                                new RedisHandler($redis, $name, $level)
                            );
                        }
                    } catch (Throwable) {
                    }
                    return $logger;
                case SyslogHandler::class:
                    $identity = $adapterOptions['identity']??null;
                    $identity = is_string($identity) ? trim($identity) : 'app_log';
                    $logger->pushHandler(new SyslogHandler(
                        $identity,
                        level: $level
                    ));
                    return $logger;
                case DatabaseHandler::class:
                    $logger->pushHandler(new DatabaseHandler(
                        $this->container->get(Connection::class),
                        $adapterOptions,
                        $level,
                    ));
                    return $logger;
            }
            if (!is_string($file)) {
                return $logger;
            }
            $currentFile = $file;
            $count = 3;
            $succeed = true;
            do {
                $dirname = dirname($currentFile);
                if (is_dir($dirname)) {
                    $succeed = is_writable($dirname);
                    break;
                }
            } while (--$count > 0);
            if (!$succeed) {
                return $logger;
            }
            $fs = new Filesystem();
            $dirname = dirname($file);
            if (!is_dir($dirname)) {
                $fs->mkdir($dirname, 0755);
            }
            $logger->pushHandler(new RotatingFileHandler(
                $currentFile,
                $maxFiles,
                $level
            ));
            return $logger;
        } finally {
            $this->reconfigureHandlerProcessor(
                $logger,
                $handlers,
                $processors
            );
        }
    }

    private function reconfigureHandlerProcessor(
        Monolog $logger,
        $handlers,
        $processors
    ): void {
        if ($handlers instanceof Config) {
            foreach ($handlers as $handler) {
                if ($handler instanceof HandlerInterface) {
                    $logger->pushHandler($handler);
                    continue;
                }
                if (is_callable($handler)) {
                    try {
                        $handler = $handler($this);
                        if ($handler instanceof HandlerInterface) {
                            $logger->pushHandler($handler);
                            continue;
                        }
                    } catch (Throwable) {
                    }
                }
            }
        }
        if ($processors instanceof Config) {
            foreach ($processors as $processor) {
                if ($processor instanceof ProcessorInterface) {
                    $logger->pushProcessor($processor);
                    continue;
                }
                if (is_callable($processor)) {
                    try {
                        $processor = $processor($this);
                        if ($processor instanceof HandlerInterface) {
                            $logger->pushHandler($processor);
                            continue;
                        }
                    } catch (Throwable) {
                    }
                }
            }
        }
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ($this->isEnable()) {
            $this->getLogger()->log($level, $message, $context);
        }
    }

    public function reset(): void
    {
        $this->logger?->reset();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getLogger()->$name(...$arguments);
    }
}
