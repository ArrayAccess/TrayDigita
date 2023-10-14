<?php
declare(strict_types=1);

use Doctrine\DBAL\Driver\PDO\MySQL\Driver as MysqlDriver;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Change this with your details
 */
return [
    /*! ENVIRONMENT CONFIG */
    'environment' => [
        // site debugging (set false in production)
        'displayErrorDetails' => false,
        // site profiling (benchmarking)
        // profiling is consume high memory footprints (set false in production)
        'profiling' => false,
        // enable debug bar (profiling must be enabled)
        'debugBar' => false,
        // debug bar dark style
        'debugBarDarkMode' => true,
        // locale / default language
        'defaultLanguage' => 'en',
        // pretty json result via event manager
        'prettyJson' => false,
        // system-wide time zone, if empty using system timezone
        'timezone' => '+00:00',
    ],
    /*! PLEASE IGNORE THIS! BEWARE OF ABOUT CHANGE THE PATH! */
    'path' => [
        /*! OPTIONAL */
        // 'controller' => __DIR__ . '/app/Controllers', // controller directory
        // 'entity' => __DIR__ . '/app/Entities', // entities directory
        // 'language' => __DIR__ . '/app/Languages', // language directory
        // 'middleware' => __DIR__ . '/app/Middlewares', // middlewares directory
        // 'migration' => __DIR__ . '/app/Migrations', // migrations directory
        // 'module' => __DIR__ . '/app/Modules', // modules path (DO NOT CHANGE!) this is contain cores
        // 'view' => __DIR__ . '/app/Views', // views path
        // 'databaseEvent' => __DIR__ . '/app/DatabaseEvents', // database events app
        // 'storage' => __DIR__ . '/storage', // temporary storage directory
        // 'data' => __DIR__ . '/data', // data upload directory
        // 'template' => 'templates' // templates path name only on public/
    ],
    /*! DATABASE CONFIG */
    'database' => [
        // database host -> default localhost
        'host' => 'localhost',
        // database user
        'user' => 'root',
        // database name
        'dbname' => 'dbname',
        // database password
        'password' => 'db_password',
        // database timezone or asia/jakarta etc
        'timezone' => '+00:00',
        // dev mode for development environment without cache (set false in production)
        'devMode' => false,
        // proxy entities directory
        'proxyDir' => __DIR__ . '/storage/database/proxy',
        // additional doctrine config
        'options' => [
        ],
        // database driver -> default mysql
        'driver' => MysqlDriver::class
    ],
    /*! LOGGING CONFIG */
    'log' => [
        // Enable logging -> or false
        'enable' => true,
        // log default name
        'name' => 'default',
        // log file
        'file' => __DIR__ .'/storage/logs/log.log',
        // log adapter default file rotate
        'adapter' => RotatingFileHandler::class,
        // the minimum log level
        'level' => Level::Error,
        // log handler to add
        'handlers' => [],
    ],
    /*! LOGGING CONFIG */
    'cache' => [
        // cache adapter, default file system
        'adapter' => FilesystemAdapter::class,
        // cache directory for file adapter
        'directory' => __DIR__ .'/storage/cache',
        // cache namespace
        'namespace' => '__',
        // default cache lifetime -> 0 is not expires
        'defaultLifetime' => 0
    ],
    /*! COOKIES CONFIG */
    'cookie' => [
        // user cookie
        'user' => [
            'name' => 'auth_user',
            'lifetime' => 0,
            'wildcard' => false
        ],
        // admin cookie
        'admin' => [
            'name' => 'auth_admin',
            'lifetime' => 0,
            'wildcard' => false
        ]
    ],
    /*! SECURITY CONFIG */
    'security' => [
        // secret key for hashing auth
        'secret' => 'random_secret_key',
        // salt for make hashing more random
        'salt' => 'random_salt_key',
        // nonce for nonce hashing
        'nonce' => 'random_nonce_key',
    ],
];
