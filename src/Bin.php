<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita;

use ArrayAccess\TrayDigita\Console\Application;
use ArrayAccess\TrayDigita\Container\Container;
use ArrayAccess\TrayDigita\Http\Exceptions\HttpException;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Exception;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionClass;
use Throwable;
use function chdir;
use function class_exists;
use function define;
use function defined;
use function dirname;
use function file_exists;
use function file_get_contents;
use function getcwd;
use function getenv;
use function in_array;
use function ini_get;
use function ini_set;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function printf;
use function realpath;
use function strlen;
use function substr;
use const DIRECTORY_SEPARATOR;
use const PHP_SAPI;
use const PHP_VERSION;
use const PHP_VERSION_ID;
use const TD_APP_DIRECTORY;
use const TD_ROOT_COMPOSER_DIR;

final class Bin
{
    // no constructor
    final private function __construct()
    {
    }

    /**
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpIssetCanBeReplacedWithCoalesceInspection
     * @noinspection DuplicatedCode
     * @throws Throwable
     */
    final public static function run()
    {
        static $run = false;
        if ($run) {
            exit(0);
        }
        $run = true;
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            if (!class_exists(Web::class)) {
                require_once __DIR__ .'/Web.php';
            }
            Web::showError(
                new Exception("Application can running on through cli only!")
            );
            exit(255);
        }

        if (PHP_VERSION_ID < 80200) {
            echo "\n\033[0;31mPhp version is not meet requirements.\033[0m\n";
            printf(
                "\033[0;34mMinimum required php version is:\033[0m \033[0;33m%s\033[0m\n",
                '8.2.0'
            );
            printf(
                "\033[0;34mInstalled php version is:\033[0m \033[0;33m%s\033[0m\n",
                PHP_VERSION
            );
            echo "\n";
            exit(255);
        }

        $cwd = getcwd();
        $classLoaderExists = false;
        $vendor = null;
        if (!class_exists(ClassLoader::class)
            && file_exists(dirname(__DIR__, 3) . '/autoload.php')
            && file_exists(dirname(__DIR__, 3) . '/composer/ClassLoader.php')
        ) {
            $vendor = dirname(__DIR__, 3);
            /** @noinspection PhpIncludeInspection */
            require $vendor . '/autoload.php';
        }

        if (class_exists(ClassLoader::class)) {
            $classLoaderExists = true;
            if (class_exists(InstalledVersions::class)) {
                $package = InstalledVersions::getRootPackage()['install_path']??null;
                $package = is_string($package) ? realpath($package) : null;
                if ($package && is_dir($package)) {
                    $root = $package;
                }
            }

            if (empty($root)) {
                $exists = false;
                $ref = new ReflectionClass(ClassLoader::class);
                $vendor = $vendor ?: dirname($ref->getFileName(), 2);
                $v = $vendor;
                $c = 3;
                do {
                    $v = dirname($v);
                } while (--$c > 0 && !($exists = file_exists($v . '/composer.json')));
                $root = $exists ? $v : dirname($vendor);
                $vendor = substr($vendor, strlen($root) + 1);
            }
        } else {
            $root = dirname(__DIR__);
            $vendor = 'vendor';
            if (!file_exists("$root/$vendor/autoload.php")) {
                if (is_file($root . '/composer.json')) {
                    $config = json_decode(file_get_contents($root . '/composer.json'), true);
                    $config = is_array($config) ? $config : [];
                    $config = isset($config['config']) ? $config['config'] : [];
                    $vendorDir = isset($config['vendor-dir']) ? $config['vendor-dir'] : null;
                    if ($vendorDir && is_dir("$root/$vendorDir")) {
                        $vendor = $vendorDir;
                    }
                }
            }
        }

        $vendorDir = $root . DIRECTORY_SEPARATOR . $vendor;
        if (!$classLoaderExists) {
            if (!file_exists("$vendorDir/autoload.php")) {
                echo "\n\033[0;31mComposer vendor directory is not exist!\033[0m\n";
                if (!is_file(TD_ROOT_COMPOSER_DIR . '/composer.json')) {
                    echo "\033[0;31mComposer file\033[0m `composer.json` \033[0;31mis not"
                        . " exists! Please check you application.\033[0m\n";
                    echo "\n";
                    exit(255);
                }
                echo "\n";
                echo "\033[0;34mPlease install dependencies via \033[0m\033[0;32m`composer install`\033[0m\n";
                echo "\n";
                exit(255);
            }
            require "$vendorDir/autoload.php";
        }

        define('TD_ROOT_COMPOSER_DIR', $root);
        // move to root
        chdir($root);
        # TD_APP_DIRECTORY='app' php bin/console
        if (!defined('TD_APP_DIRECTORY')) {
            $appDir = getenv('TD_APP_DIRECTORY')?: null;
            if ($appDir && is_string($appDir)) {
                $appDir = realpath($appDir) ?: (
                    realpath($cwd . DIRECTORY_SEPARATOR . $appDir) ?: (
                        realpath(TD_ROOT_COMPOSER_DIR . DIRECTORY_SEPARATOR . $appDir) ?: null
                    )
                );
            }

            $appDir = isset($appDir) ? $appDir : TD_ROOT_COMPOSER_DIR . DIRECTORY_SEPARATOR . 'app';
            /*if (!is_dir($appDir)) {
                echo "\n\033[0;31mCould not detect application directory\033[0m\n";
                echo "\n";
                exit(255);
            }*/
            define('TD_APP_DIRECTORY', $appDir);
        } elseif (!is_string(TD_APP_DIRECTORY)) {
            echo "\n\033[0;31mConstant \033[0;0m`TD_APP_DIRECTORY`\033[0;31m is invalid\033[0m\n";
            echo "\n";
            exit(255);
        } elseif (!is_dir(TD_APP_DIRECTORY)) {
            echo "\n\033[0;31mApplication directory is not exists\033[0m\n";
            echo "\n";
            exit(255);
        }

        // disable opcache
        Consolidation::callbackReduceError(static function () {
            ini_set('opcache.enable_cli', '0');
            ini_set('opcache.enable', '0');
        });

        $kernel = Decorator::init(); // do lock
        Consolidation::callbackReduceError(static function () {
            $memory_limit = ini_get('memory_limit');
            if (!$memory_limit
                // if less than 64MB
                || Consolidation::returnBytes($memory_limit) < 67108864
            ) {
                // force to use 128M
                ini_set('memory_limit', '128M');
            }
        });

        // append cli
        $_SERVER['REQUEST_METHOD'] = 'CLI';
        $_SERVER['CURRENT_TASK'] = 'CONSOLE';

        // boot
        $kernel->boot();

        /**
         * @var Container $container
         */
        $container = $kernel->getContainer();
        $manager = $kernel->getManager();
        $console = $container->get(Application::class);
        $console->setAutoExit(false);

        $manager->dispatch('console.run', $console);
        $request = ServerRequest::fromGlobals(
            $kernel->getContainer()->get(ServerRequestFactoryInterface::class),
            $kernel->getContainer()->get(StreamFactoryInterface::class)
        );

        $exitCode = 0;
        $run = false;
        // disable emit
        $manager->attach('httpKernel.emitResponse', fn () => false);

        // add event on kernelAfter dispatch
        $manager->attach(
            'httpKernel.afterDispatch',
            static function () use ($console, &$exitCode, &$run) {
                $run = true;
                $console->setAutoExit(false);
                $exitCode = $console->run();
            }
        );

        try {
            $kernel->handle($request);
        } catch (HttpException) {
        }
        if (!$run) {
            $console->setAutoExit(false);
            $exitCode = $console->run();
        }
        $kernel->shutdown();
        exit($exitCode);
    }
}
