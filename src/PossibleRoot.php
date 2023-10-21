<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use ReflectionClass;
use function class_exists;
use function debug_backtrace;
use function defined;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function realpath;
use function strpos;
use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const TD_APP_DIRECTORY;

final class PossibleRoot
{
    private static string|bool|null $rootDirectoryResolved = null;

    private static array $composerJsonConfig = [];

    /**
     * @return ?string
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpIssetCanBeReplacedWithCoalesceInspection
     */
    public static function getPossibleRootDirectory()
    {
        if (self::$rootDirectoryResolved !== null) {
            return self::$rootDirectoryResolved?:null;
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        /** @noinspection PhpStrFunctionsInspection */
        $inside = isset($trace['file']) && strpos($trace['file'], __DIR__) === 0;
        // check if as library
        if (!class_exists(ClassLoader::class)) {
            // if as library
            if (file_exists(dirname(__DIR__, 3) . '/autoload.php')
                && file_exists(dirname(__DIR__, 3) . '/composer/ClassLoader.php')
            ) {
                /** @noinspection PhpIncludeInspection */
                require dirname(__DIR__, 3) . '/autoload.php';
            } elseif ($inside) {
                $root = dirname(__DIR__);
                if (file_exists("$root/vendor/autoload.php")) {
                    require "$root/vendor/autoload.php";
                } elseif (is_file($root . '/composer.json')) {
                    $composerJson = $root . '/composer.json';
                    $composerJson = realpath($composerJson)?:$composerJson;
                    self::$composerJsonConfig[$composerJson] = $root;
                    $config = json_decode(file_get_contents($composerJson), true);
                    $config = is_array($config) ? $config : [];
                    $config = isset($config['config']) ? $config['config'] : [];
                    $vendorDirectory = isset($config['vendor-dir']) ? $config['vendor-dir'] : null;
                    if ($vendorDirectory && file_exists("$root/$vendorDirectory/autoload.php")) {
                        require "$root/$vendorDirectory/autoload.php";
                    }
                }
            }
        }

        if (class_exists(ClassLoader::class)) {
            if (class_exists(InstalledVersions::class)) {
                $package = InstalledVersions::getRootPackage()['install_path']?:null;
                $package = is_string($package) ? realpath($package) : null;
                if ($package && is_dir($package)) {
                    $root = $package;
                    if ($inside) {
                        self::$rootDirectoryResolved  = $root;
                    }
                    return $root;
                }
            }
            if (empty($root)) {
                $ref = new ReflectionClass(ClassLoader::class);
                $vendor = dirname($ref->getFileName(), 2);
                $exists = false;
                $v = $vendor;
                $c = 3;
                do {
                    $v = dirname($v);
                } while (--$c > 0 && !($exists = file_exists($v . '/composer.json')));
                if ($exists) {
                    if ($inside) {
                        self::$rootDirectoryResolved = $v;
                    }
                    return $v;
                }
            }
        }

        if (defined('TD_APP_DIRECTORY')
            && is_string(TD_APP_DIRECTORY)
            && dirname(TD_APP_DIRECTORY) . '/vendor'
        ) {
            $exists = false;
            $v = TD_APP_DIRECTORY;
            $c = 3;
            do {
                $v = dirname($v);
            } while (--$c > 0 && !($exists = file_exists($v . '/composer.json')));
            $root = $exists ? $v : dirname(TD_APP_DIRECTORY);
            $composerJson = "$root/composer.json";
            $composerJson = realpath($composerJson)?:$composerJson;
            if (isset(self::$composerJsonConfig[$composerJson])) {
                $root = self::$composerJsonConfig[$composerJson];
            } else {
                if (is_file($composerJson)) {
                    $config = json_decode(file_get_contents($composerJson), true);
                    $config = is_array($config) ? $config : [];
                    $config = isset($config['config']) ? $config['config'] : [];
                }
                $vendorDirectory = isset($config['vendor-dir']) ? $config['vendor-dir'] : null;
                if (!is_string($vendorDirectory) || ! is_dir("$root/$vendorDirectory")) {
                    $root = dirname(TD_APP_DIRECTORY);
                }
                self::$composerJsonConfig[$composerJson] = $root;
            }
            if ($inside) {
                self::$rootDirectoryResolved = $root;
            }
            if (!is_dir($root)) {
                return null;
            }
            return $root;
        }

        return self::$rootDirectoryResolved;
    }
}
