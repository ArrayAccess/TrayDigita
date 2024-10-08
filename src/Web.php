<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Http\RequestResponseExceptions\RequestSpecializedCodeException;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\MimeType;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Throwable;
use function basename;
use function class_alias;
use function class_exists;
use function define;
use function defined;
use function dirname;
use function explode;
use function header;
use function headers_sent;
use function in_array;
use function interface_exists;
use function is_file;
use function is_string;
use function ob_end_clean;
use function ob_get_length;
use function ob_get_level;
use function php_sapi_name;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function printf;
use function realpath;
use const DIRECTORY_SEPARATOR;
use const PHP_SAPI;
use const PHP_VERSION_ID;
use const TD_INDEX_FILE;

final class Web
{
    /**
     * @var string[] $blackListedExtensions List of blacklisted extensions
     */
    protected static array $blackListedExtensions = [
        'php',
        'cgi',
        'pl',
        'py',
        'rb',
        'sh',
        'jsp',
        'asp',
        'aspx',
        'cfm',
    ];

    // no construct
    final private function __construct()
    {
    }

    /**
     * Get the blacklisted extensions
     *
     * @return string[]
     */
    public static function getBlackListedExtensions(): array
    {
        return self::$blackListedExtensions;
    }

    /**
     * Check if the extension is blacklisted
     *
     * @param string $extension
     * @return bool
     */
    public static function isExtensionBlackListed(string $extension): bool
    {
        return in_array(strtolower($extension), self::getBlackListedExtensions(), true);
    }

    /**
     * Set the blacklisted extensions
     *
     * @param array<string> $blackListedExtensions
     */
    public static function setBlackListedExtensions(array $blackListedExtensions): void
    {
        $blackListedExtensions = array_filter($blackListedExtensions, 'is_string');
        $blackListedExtensions = array_map('strtolower', $blackListedExtensions);
        $blackListedExtensions = array_values(array_filter(array_unique($blackListedExtensions)));
        if (!in_array('php', $blackListedExtensions, true)) {
            // always disallow php
            $blackListedExtensions[] = 'php';
        }
        self::$blackListedExtensions = $blackListedExtensions;
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface|false
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpIssetCanBeReplacedWithCoalesceInspection
     * @noinspection PhpFullyQualifiedNameUsageInspection
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

            if (!class_exists(PossibleRoot::class)) {
                require_once __DIR__ .'/PossibleRoot.php';
            }

            $root = PossibleRoot::getPossibleRootDirectory();
            if (!$root) {
                throw new RuntimeException(
                    "Could not detect root directory"
                );
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
            $publicDirectory = dirname($publicFile);
            // HANDLE CLI-SERVER
            if (php_sapi_name() === 'cli-server') {
                if ($_SERVER['DOCUMENT_ROOT'] !== $publicDirectory) {
                    throw new RuntimeException(
                        "Builtin web server should be pointing into public root directory!"
                    );
                }

                $requestUri = $_SERVER['REQUEST_URI'];
                $requestUriNoQuery = explode('?', $requestUri, 2)[0];
                $originalScriptFileName = $_SERVER['SCRIPT_FILENAME'];
                $originalScriptName = $_SERVER['SCRIPT_NAME']??$_SERVER['PHP_SELF']??null;
                $isScriptFileNameMatch = $originalScriptFileName === __FILE__;
                $baseName = basename($requestUriNoQuery);
                $extension = str_contains('.', $baseName)
                    ? pathinfo($baseName, PATHINFO_EXTENSION)
                    : null;
                $extension = $extension && preg_match('~^[a-zA-Z]+$~', $extension)
                    ? strtolower($extension)
                    : null;

                /**
                 * If extension matches & exists return false
                 * that means the builtin web server will serve static assets
                 */
                // check mimetypes
                if ($extension && self::isExtensionBlackListed($extension)
                    && is_file($publicDirectory . '/' . $requestUriNoQuery)
                    && is_readable($publicDirectory . '/' . $requestUriNoQuery)
                ) {
                    $realPath = realpath($publicDirectory . '/' . $requestUriNoQuery);
                    $realPathOriginal = $originalScriptName
                        ? realpath($publicDirectory . '/' . $originalScriptName)
                        : null;
                    if (!headers_sent()) {
                        $mimeType = MimeType::mime($extension) ?: 'application/octet-stream';
                        header('Content-Type: ' . $mimeType);
                    }
                    // if the script file name matches the original script file name
                    if (!$isScriptFileNameMatch
                        && $realPath
                        && (
                            $realPath === $originalScriptFileName
                            || (
                                // resolve the real path of the original script name
                                $originalScriptName && $realPath === $realPathOriginal
                            )
                        )
                    ) {
                        readfile($realPath);
                    }

                    // serve the static assets with return : false
                    return $lastResult = false;
                }

                $_SERVER['PHP_SELF'] = DIRECTORY_SEPARATOR.  basename(__FILE__);
                $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
            }

            if (!defined('TD_APP_DIRECTORY')) {
                // DEFINE : TD_APP_DIRECTORY
                define('TD_APP_DIRECTORY', dirname($publicDirectory) . DIRECTORY_SEPARATOR . 'app');
            }

            /**
             * @var \ArrayAccess\TrayDigita\Kernel\Kernel $kernel
             * @noinspection PhpFullyQualifiedNameUsageInspection
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
     * @param $e
     * @param $kernel
     * @param $sentHeader
     * @return void
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function showError($e, $kernel = null, $sentHeader = true)
    {
        if (!class_exists('Throwable')
            && !interface_exists('Throwable')
        ) {
            /** @noinspection PhpIgnoredClassAliasDeclaration */
            class_alias('Exception', 'Throwable');
        }

        if (!$e instanceof Throwable) {
            return;
        }
        $level = ob_get_level();
        if ($level && ob_get_length()) {
            do {
                ob_end_clean();
            } while (ob_get_length() && ob_get_level() > 0);
        }
        $code = 500;
        $title = '500 Internal Server Error';
        $errorDescription = 'Internal Server Error';
        if (class_exists(RequestSpecializedCodeException::class)
            && $e instanceof RequestSpecializedCodeException
        ) {
            $code = $e->getCode();
            $title = $e->getTitle();
            $errorDescription = $e->getDescription();
        }

        if ($sentHeader && !headers_sent()) {
            header('Content-Type: text/html', true, $code);
        }

        if (!class_exists(PossibleRoot::class)) {
            require_once __DIR__ .'/PossibleRoot.php';
        }
        $root = PossibleRoot::getPossibleRootDirectory();
        if (!$root && isset($_SERVER['DOCUMENT_ROOT'])) {
            $root = realpath($_SERVER['DOCUMENT_ROOT']);
        }
        $root = is_string($root) ? $root : dirname(__DIR__);
        $root = preg_quote($root, '~');
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
            $enable = $config instanceof Config && (
                    $config->get('displayErrorDetails') === true
                    || $config->get('debug') === true
                );
        }
        $additionalText = ! $enable ? "<p><code>$message</code></p>" : <<<HTML
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
