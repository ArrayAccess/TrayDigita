<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Http\RequestResponseExceptions\RequestSpecializedCodeException;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Kernel\Kernel;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;
use function basename;
use function class_alias;
use function class_exists;
use function define;
use function defined;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function header;
use function headers_sent;
use function in_array;
use function interface_exists;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function ob_end_clean;
use function ob_get_length;
use function ob_get_level;
use function php_sapi_name;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function printf;
use function realpath;
use function strlen;
use function substr;
use function var_dump;
use const DIRECTORY_SEPARATOR;
use const PHP_SAPI;
use const PHP_VERSION_ID;
use const TD_INDEX_FILE;

// phpcs:disable PSR1.Files.SideEffects
if (!class_exists('Throwable')
    && !interface_exists('Throwable')
) {
    /** @noinspection PhpIgnoredClassAliasDeclaration */
    class_alias('Exception', 'Throwable');
}

final class Web
{
    // no construct
    final private function __construct()
    {
    }

    /**
     * @return ResponseInterface|false
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpIssetCanBeReplacedWithCoalesceInspection
     * @noinspection DuplicatedCode
     */
    final public static function serve()
    {
        // mutable static
        static $lastResult = null;

        if ($lastResult !== null) {
            return $lastResult;
        }

        // DISALLOW CLI
        if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            echo "\033[0;31mIndex file could not include in CLI Mode!\033[0m\n";
            printf(
                "Please consider using \033[0;32mbin/console\033[0m application\n"
            );
            exit(255);
        }

        //  START INIT EXCEPTION LISTENER
        try {
            // PHP VERSION MUST BE 8.2 OR LATER
            if (PHP_VERSION_ID < 80200) {
                throw new RuntimeException(
                    "Php version is not meet requirement. Minimum required php version is: 8.2"
                );
            }

            $classLoaderExists = false;
            $vendor = null;
            if (!class_exists(ClassLoader::class)
                && file_exists(dirname(__DIR__, 3) . '/autoload.php')
                && file_exists(dirname(__DIR__, 3) . '/composer/ClassLoader.php')
            ) {
                /** @noinspection PhpIncludeInspection */
                $vendor = dirname(__DIR__, 3);
                /** @noinspection PhpIncludeInspection */
                require $vendor . '/autoload.php';
            }

            if (class_exists(ClassLoader::class)) {
                $classLoaderExists = true;
                $exists = false;
                $ref = new ReflectionClass(ClassLoader::class);
                if (class_exists(InstalledVersions::class)) {
                    $package = InstalledVersions::getRootPackage()['install_path']??null;
                    $package = is_string($package) ? realpath($package) : null;
                    if ($package && is_dir($package)) {
                        $root = $package;
                    }
                }
                if (empty($root)) {
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

                // CHECK COMPOSER AUTOLOADER
                if (!file_exists("$root/$vendor/autoload.php")) {
                    throw new RuntimeException(
                        "Composer autoloader is not exists"
                    );
                }
            }

            $publicFile = null;
            if (!defined('TD_INDEX_FILE')) {
                if (isset($_SERVER['SCRIPT_FILENAME'])) {
                    $publicFile = realpath($_SERVER['SCRIPT_FILENAME']);
                }
                if (!$publicFile) {
                    throw new RuntimeException(
                        'Could not detect public index file'
                    );
                }
                define('TD_INDEX_FILE', $publicFile);
            }
            if (!is_string(TD_INDEX_FILE)) {
                throw new RuntimeException(
                    'Constant "TD_INDEX_FILE" is not valid public file!'
                );
            }
            if (!is_file(TD_INDEX_FILE)) {
                throw new RuntimeException(
                    'Public file does not exists!'
                );
            }
            $publicFile = $publicFile ?: realpath(TD_INDEX_FILE);
            $publicDir = dirname($publicFile);
            // HANDLE CLI-SERVER
            if (php_sapi_name() === 'cli-server') {
                if ($_SERVER['DOCUMENT_ROOT'] !== $publicDir) {
                    throw new RuntimeException(
                        "Builtin web server should be pointing into public root directory!"
                    );
                }

                $requestUri = $_SERVER['REQUEST_URI'];
                $requestUriNoQuery = explode('?', $requestUri, 2)[0];

                /**
                 * If extension matches & exists return false
                 * that means the builtin web server will serve static assets
                 */
                // check mimetypes
                if (preg_match(
                    '~\.(?:
                ics|ico|je|jpe?g|png|gif|webp|svg|tiff?|bmp      # image
                |css|jsx?|x?html?|xml|xsl|xsd|ja?son             # web assets
                |te?xt|docx?|pptx?|xlsx?|csv|pdf|swf|pps|txt     # document
                |mp[34]|og[gvpa]|mpe?g|3gp|avi|mov|flac|flv|webm|wmv # media
            )$~ix',
                    $requestUriNoQuery
                ) && is_file($publicDir . '/' . $requestUriNoQuery)) {
                    // serve the static assets with return : false
                    return $lastResult = false;
                }

                $_SERVER['PHP_SELF'] = '/' . basename(__FILE__);
                $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
            }

            if (!defined('TD_APP_DIRECTORY')) {
                // DEFINE : TD_APP_DIRECTORY
                define('TD_APP_DIRECTORY', dirname($publicDir) . DIRECTORY_SEPARATOR . 'app');
            }

            if (!$classLoaderExists) {
                // INCLUDE COMPOSER AUTOLOADER
                require "$root/$vendor/autoload.php";
            }

            /**
             * @var Kernel $kernel
             */
            $kernel = Decorator::kernel();
            $kernel->init();
            $request = ServerRequest::fromGlobals(
                Decorator::service(ServerRequestFactoryInterface::class),
                Decorator::service(StreamFactoryInterface::class)
            );
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            $kernel->shutdown();
            return $lastResult = $kernel->dispatchResponse($response);
        } catch (Throwable $e) {
            /** @noinspection PhpIssetCanBeReplacedWithCoalesceInspection */
            self::showError($e, isset($kernel) ? $kernel : null);
            exit(255);
        }
    }

    /**
     * @param Throwable $e
     * @param $kernel
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function showError(Throwable $e, $kernel = null)
    {
        $level = ob_get_level();
        if ($level && ob_get_length()) {
            do {
                ob_end_clean();
            } while (ob_get_length() && ob_get_level() > 0);
        }
        $code = 500;
        $title = '500 Internal Server Error';
        $errorDescription = 'Internal Server Error';
        if ($e instanceof RequestSpecializedCodeException) {
            $code = $e->getCode();
            $title = $e->getTitle();
            $errorDescription = $e->getDescription();
        }

        if (!headers_sent()) {
            header('Content-Type: text/html', true, $code);
            $root = preg_quote(dirname(__DIR__), '~');
            $file = preg_replace("~{$root}[\\\/]~m", '', $e->getFile());
            $message = preg_replace("~{$root}[\\\/]~m", '', $e->getMessage());
            $trace = preg_replace("~{$root}[\\\/]~m", '', $e->getTraceAsString());
            $line = $e->getLine();
            $enable = false;
            if (class_exists(ContainerHelper::class) && isset($kernel)
                && $kernel instanceof KernelInterface
            ) {
                $config = ContainerHelper::use(Config::class, $kernel->getHttpKernel()->getContainer());
                $config = $config->get('environment');
                $enable = $config instanceof Config && $config->get('displayErrorDetails') === true;
            }
            $additionalText = !$enable ? '' : <<<HTML
<p><code>$message</code></p>
<p><code>$file:($line)</code></p>
<pre>$trace</pre>
HTML;
            echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
        body {
            padding:0;
            margin: 0;
            font-size: 16px;
            line-height: 1.15;
            color: #222;
            -webkit-text-size-adjust: 100%;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, 
                "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans",
                sans-serif, "Apple Color Emoji", "Segoe UI Emoji",
                "Segoe UI Symbol", "Noto Color Emoji";
                background: #f1f1f1;
        }
        h1 {
            font-size: 4rem;
            margin:1rem 0 .7rem;
            border-left: 3px solid;
            padding-left: 1rem;
        }
        h2 {
            margin:0 0 1rem;
            font-size: 1rem;
            letter-spacing: 1px;
        }
        body > div {
            padding: 1rem;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div>
        <h1>$code</h1>
        <h2>$errorDescription</h2>
        $additionalText
    </div>
</body>
</html>
HTML;
        }
    }
}
