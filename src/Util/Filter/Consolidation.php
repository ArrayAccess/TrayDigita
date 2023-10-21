<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\PossibleRoot;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
use Stringable;
use function array_key_exists;
use function array_values;
use function class_exists;
use function doubleval;
use function explode;
use function file_exists;
use function get_class;
use function get_object_vars;
use function gettype;
use function implode;
use function in_array;
use function ini_get;
use function intval;
use function is_array;
use function is_callable;
use function is_dir;
use function is_file;
use function is_int;
use function is_iterable;
use function is_numeric;
use function is_object;
use function is_readable;
use function is_string;
use function ltrim;
use function min;
use function number_format;
use function parse_str;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function realpath;
use function restore_error_handler;
use function rtrim;
use function set_error_handler;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function spl_object_hash;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrchr;
use function strstr;
use function strtolower;
use function substr;
use function trim;
use function urlencode;
use const DIRECTORY_SEPARATOR;
use const PHP_SAPI;

class Consolidation
{
    final const BLACKLISTED_NAME = [
        'array',
        'iterable',
        'string',
        'false',
        'bool',
        'boolean',
        'object',
        'default',
        'switch',
        'case',
        'if',
        'elseif',
        'else',
        'while',
        'for',
        'foreach',
        'match',
        'fn',
        'function',
        'const',
        'class',
        'float',
        'and',
        'null',
        'or',
        'private',
        'public',
        'protected',
        'final',
        'abstract',
        'as',
        'break',
        'continue',

        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare	',
        'endfor	',
        'endforeach	',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'match',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'return',
        'static',
        'self',
        'parent',
        // reserved
        '__halt_compiler',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
        'list',
    ];

    public static function allowedClassName(string $className): bool
    {
        if (!self::isValidClassName($className)) {
            return false;
        }
        foreach (explode('\\', $className) as $className) {
            if (in_array(strtolower($className), self::BLACKLISTED_NAME)) {
                return false;
            }
        }
        return true;
    }

    public static function mapDeep($value, callable $callback)
    {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = self::mapDeep($item, $callback);
            }
        } elseif (is_object($value)) {
            $object_vars = get_object_vars($value);
            foreach ($object_vars as $property_name => $property_value) {
                $value->$property_name = self::mapDeep($property_value, $callback);
            }
        } else {
            $value = $callback($value);
        }

        return $value;
    }

    /**
     * @param $data
     * @param string|null $prefix
     * @param string|null $sep
     * @param string $key
     * @param bool $urlEncode
     * @return string
     */
    public static function buildQuery(
        $data,
        string $prefix = null,
        string $sep = null,
        string $key = '',
        bool $urlEncode = true
    ): string {
        $ret = [];

        foreach ((array)$data as $k => $v) {
            if ($urlEncode) {
                $k = urlencode((string) $k);
            }
            if (is_int($k) && null !== $prefix) {
                $k = $prefix . $k;
            }
            if (!empty($key)) {
                $k = $key . '%5B' . $k . '%5D';
            }
            if (null === $v) {
                continue;
            } elseif (false === $v) {
                $v = '0';
            }

            if (is_array($v) || is_object($v)) {
                $ret[] = self::buildQuery($v, '', $sep, $k, $urlEncode);
            } elseif ($urlEncode) {
                $ret[] = $k . '=' . urlencode((string) $v);
            } else {
                $ret[] = $k . '=' . $v;
            }
        }

        if (null === $sep) {
            $sep = ini_get('arg_separator.output');
        }

        return implode($sep, $ret);
    }

    /**
     * @param mixed ...$args
     * @return string
     */
    public static function addQueryArgs(...$args): string
    {
        if (!isset($args[0])) {
            return '';
        }
        $uri_ = $args[0];

        if (is_array($uri_)) {
            if (count($args) < 2 || false === $args[1]) {
                $uri = $_SERVER['REQUEST_URI']; //$_SERVER['REQUEST_URI'];
            } else {
                $uri = $args[1];
            }
        } else {
            if (count($args) < 3 || false === $args[2]) {
                if (is_string($args[0]) && preg_match('#^([^:]+://|/)#i', $args[0])) {
                    $uri = $args[0];
                    unset($args[0]);
                    $args = array_values($args);
                } elseif (is_string($args[1]) && preg_match('#^([^:]+://|/)#i', $args[1])) {
                    $uri = $args[1];
                    unset($args[1]);
                    $args = array_values($args);
                } else {
                    $uri = $_SERVER['REQUEST_URI']; // ['REQUEST_URI'];
                }
            } else {
                $uri = $args[2];
            }
        }

        $frag = strstr($uri, '#');
        if ($frag) {
            $uri = substr($uri, 0, -strlen($frag));
        } else {
            $frag = '';
        }

        if (preg_match('~^(https?://)(.+)$~i', $uri, $match)) {
            $protocol = strtolower($match[1]);
            $uri = $match[2];
        } else {
            $protocol = '';
        }

        if (str_contains($uri, '?')) {
            [$base, $query] = explode('?', $uri, 2);
            $base .= '?';
        } elseif ($protocol || !str_contains($uri, '=')) {
            $base = $uri . '?';
            $query = '';
        } else {
            $base = '';
            $query = $uri;
        }

        parse_str($query, $qs);

        $qs = self::mapDeep($qs, 'urldecode');
        // $qs = self::mapDeep($qs, 'urlencode');
        if (is_array($args[0])) {
            foreach ($args[0] as $k => $v) {
                $qs[$k] = $v;
            }
        } elseif (isset($args[1])) {
            $qs[$args[0]] = $args[1];
        }

        foreach ($qs as $k => $v) {
            if (false === $v) {
                unset($qs[$k]);
            }
        }

        $ret = self::buildQuery($qs);
        $ret = trim($ret, '?');
        $ret = preg_replace('#=(&|$)#', '$1', $ret);
        $ret = $protocol . $base . $ret . $frag;

        return rtrim($ret, '?');
    }

    /**
     * Removes an item or items from a query string.
     *
     * @param array|string $key Query key or keys to remove.
     * @param bool|string $query Optional. When false uses the current URL. Default false.
     *
     * @return string New URL query string.
     */
    public static function removeQueryArg(array|string $key, bool|string $query = false): string
    {
        if (is_array($key)) {
            // Removing multiple keys.
            foreach ($key as $k) {
                $query = self::addQueryArgs($k, false, $query);
            }

            return $query;
        }
        return self::addQueryArgs($key, false, $query);
    }

    /**
     * @param callable $callback
     * @param $errNo
     * @param $errStr
     * @param $errFile
     * @param $errLine
     * @param $errContext
     *
     * @return mixed
     */
    public static function callbackReduceError(
        callable $callback,
        &$errNo = null,
        &$errStr = null,
        &$errFile = null,
        &$errLine = null,
        &$errContext = null
    ): mixed {
        set_error_handler(static function (
            $no,
            $str,
            $file,
            $line,
            $c = null
        ) use (
            &$errNo,
            &$errStr,
            &$errFile,
            &$errLine,
            &$errContext
        ) {
            $errNo = $no;
            $errStr = $str;
            $errFile = $file;
            $errLine = $line;
            $errContext = $c;
        });
        $result = $callback();
        restore_error_handler();

        return $result;
    }

    public static function namespace(string|object $fullClassName) : string|false
    {
        if (is_object($fullClassName)) {
            return (new ReflectionObject($fullClassName))->getNamespaceName();
        }
        if (!self::isValidClassName($fullClassName)) {
            return false;
        }
        $className = ltrim($fullClassName, '\\');
        return preg_replace('~^(.+)?\\\[^\\\]+$~', '$1', $className);
    }

    public static function isValidClassName(string $className) : bool
    {
        return (bool) preg_match(
            '~^\\\?[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*(?:\\\[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*)*$~',
            $className
        );
    }

    /**
     * @param string|object $fullClassName
     * @param bool $real
     * @return string
     */
    public static function classShortName(
        string|object $fullClassName,
        bool $real = false
    ): string {
        if (is_object($fullClassName)) {
            if ($real) {
                return (new ReflectionObject($fullClassName))->getShortName();
            }
            $fullClassName = $fullClassName::class;
        } elseif ($real && class_exists($fullClassName)) {
            return (new ReflectionClass($fullClassName))->getShortName();
        }

        return str_contains($fullClassName, '\\')
            ? substr(
                strrchr($fullClassName, '\\'),
                1
            ) : $fullClassName;
    }

    public static function isValidFunctionName(string $name) : bool
    {
        return (bool) preg_match('~[_a-zA-Z\x80-\xff]+[a-zA-Z0-9_\x80-\xff]*$~', $name);
    }

    public static function isValidVariableName(string $name) : bool
    {
        return (bool) preg_match('~[_a-zA-Z\x80-\xff]+[a-zA-Z0-9_\x80-\xff]*$~', $name);
    }

    public static function isCli() : bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * @return bool
     */
    public static function isWindows() : bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * @return bool
     */
    public static function isUnix() : bool
    {
        return DIRECTORY_SEPARATOR === '/';
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function isRelativePath(string $path) : bool
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        return (bool) (self::isWindows() ? preg_match('~^[A-Za-z]+:\\\~', $path) : preg_match('~^/~', $path));
    }

    /**
     * Doing require file with no $this object
     *
     * @param string $file
     * @param bool $once
     * @param array $arguments
     * @param null $found
     *
     * @return mixed
     */
    public static function requireNull(
        string $file,
        bool $once = false,
        array $arguments = [],
        &$found = null
    ): mixed {
        $found = is_file($file) && is_readable($file);

        return $found
            ? (static fn($arguments) => $once ? require_once $file : require $file)->bindTo(null)($arguments)
            : false;
    }

    /**
     * Doing include file with no $this object
     *
     * @param string $file
     * @param bool $once
     * @param $found
     *
     * @return mixed
     */
    public static function includeNull(string $file, bool $once = false, &$found = null): mixed
    {
        $found = is_file($file) && is_readable($file);
        return $found
            ? (static fn() => $once ? include_once $file : include $file)->bindTo(null)()
            : false;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function convertNotationValue(mixed $value): mixed
    {
        $annotator = [
            'true' => true,
            'TRUE' => true,
            'false' => false,
            'FALSE' => false,
            'NULL' => null,
            'null' => null,
        ];
        if (is_string($value)) {
            if (is_numeric($value)) {
                return str_contains($value, '.') ? (float)$value : (int)$value;
            }
            return array_key_exists($value, $annotator) ? $annotator[$value] : $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = self::convertNotationValue($val);
            }
        }

        return $value;
    }

    /**
     * Object binding suitable to call private method
     *
     * @param Closure $closure
     * @param object $object
     *
     * @return Closure
     * @throws RuntimeException|ReflectionException
     */
    public static function objectBinding(
        Closure $closure,
        object $object
    ): Closure {
        $reflectedClosure = new ReflectionFunction($closure);
        $isBindable = (
            ! $reflectedClosure->isStatic()
            || ! $reflectedClosure->getClosureScopeClass()
            || $reflectedClosure->getClosureThis() !== null
        );
        if (!$isBindable) {
            throw new RuntimeException(
                'Cannot bind an instance to a static closure.'
            );
        }

        return $closure->bindTo($object, get_class($object));
    }

    /**
     * Call object binding
     *
     * @param Closure $closure
     * @param object $object
     * @param ...$args
     *
     * @return mixed
     * @throws RuntimeException|ReflectionException
     */
    public static function callObjectBinding(Closure $closure, object $object, ...$args): mixed
    {
        return self::objectBinding($closure, $object)(...$args);
    }

    /**
     * Convert number of bytes the largest unit bytes will fit into.
     *
     * It is easier to read 1 kB than 1024 bytes and 1 MB than 1048576 bytes. Converts
     * number of bytes to human-readable number by taking the number of that unit
     * that the bytes will go into it. Supports TB value.
     *
     * Please note that integers in PHP are limited to 32 bits, unless they are on
     * 64 bit architecture, then they have 64 bit size. If you need to place the
     * larger size then what PHP integer type will hold, then use a string. It will
     * be converted to a double, which should always have 64 bit length.
     *
     * Technically the correct unit names for powers of 1024 are KiB, MiB etc.
     *
     * @param int|float $bytes         Number of bytes. Note max integer size for integers.
     * @param int $decimals      Optional. Precision of number of decimal places. Default 0.
     * @param string     $decimalPoint Optional decimal point
     * @param string $thousandSeparator Optional thousand separator
     * @param bool $removeZero if decimal contain zero, remove it
     *
     * @return string size unit
     */
    public static function sizeFormat(
        int|float $bytes,
        int $decimals = 0,
        string $decimalPoint = '.',
        string $thousandSeparator = ',',
        bool $removeZero = true
    ): string {
        $quanta = [
            // ========================= Origin ====
            // 'YB' => 1208925819614629174706176,  // pow( 1024, 8)
            // 'ZB' => 1180591620717411303424,  // pow( 1024, 7) << bigger than PHP_INT_MAX is 9223372036854775807
            'EB' => 1152921504606846976,  // pow( 1024, 6)
            'PB' => 1125899906842624,  // pow( 1024, 5)
            'TB' => 1099511627776,  // pow( 1024, 4)
            'GB' => 1073741824,     // pow( 1024, 3)
            'MB' => 1048576,        // pow( 1024, 2)
            'KB' => 1024,           // pow( 1024, 1)
            'B'  => 1,              // 1
        ];

        /**
         * Check and did
         */
        $currentUnit = 'B';
        foreach ($quanta as $unit => $mag) {
            if (doubleval($bytes) >= $mag) {
                $result = number_format(
                    ($bytes / $mag),
                    $decimals,
                    $decimalPoint,
                    $thousandSeparator
                );
                $currentUnit = $unit;
                break;
            }
        }

        $result = $result??number_format(
            $bytes,
            $decimals,
            $decimalPoint,
            $thousandSeparator
        );
        if ($removeZero) {
            $result = preg_replace('~\.0+$~', '', $result);
        }
        return "$result $currentUnit";
    }

    /**
     * Convert number of unit just for Ki(not kilo) metric based on 1024 (binary unit)
     *
     * @param string $size number with unit name 10M or 10MB
     * @return int
     */
    public static function returnBytes(string $size) : int
    {
        $size = trim($size) ?: 0;
        if (!$size) {
            return 0;
        }
        // get size unit (MB = MiB = MIB = mib) case-insensitive
        // invalid format will return exponent of 1
        preg_match(
            '~[0-9]\s*([yzeptgmk]i?b|[yzeptgmkb])$~',
            strtolower($size),
            $match
        );
        // patch tolerant
        return intval($size) * (match ($match[1]??null) {
                'y', 'yb' => 1208925819614629174706176, // yottabyte
                'z', 'zb' => 1180591620717411303424, // zettabyte << bigger than PHP_INT_MAX is 9223372036854775807
                'e', 'eb' => 1152921504606846976, // exabyte
                'p', 'pb' => 1125899906842624, // petabyte
                't', 'tb' => 1099511627776, // terabyte
                'g', 'gb' => 1073741824, // gigabyte
                'm', 'mb' => 1048576, // megabyte
                'k', 'kb' => 1024, // kilobyte
                default => 1 // byte
        });
    }

    /**
     * @return int
     */
    public static function getMaxUploadSize() : int
    {
        $data = [
            self::returnBytes(ini_get('post_max_size')),
            self::returnBytes(ini_get('upload_max_filesize')),
            (self::returnBytes(ini_get('memory_limit')) - 2048),
        ];
        foreach ($data as $key => $v) {
            if ($v <= 0) {
                unset($data[$key]);
            }
        }

        return min($data);
    }
    public static function debugInfo(
        object $object,
        array $keyRedacted = [],
        ?string $regexP = null,
        array $excludeKeys = [],
        bool $detectSubKey = false,
        bool $detectParent = true,
    ): array {
        $regexP = $regexP && DataType::isValidRegExP($regexP) ? $regexP : null;
        $reflectionObject = new ReflectionObject($object);
        if ($reflectionObject->isInternal()) {
            return get_object_vars($object);
        }
        $oldInfo = (fn() => get_object_vars($this))->call($object);
        if (!is_array($oldInfo)) {
            return [];
        }
        $info = [];
        foreach ($oldInfo as $item => $value) {
            unset($oldInfo[$item]);
            $keyItem = $item;
            if ($reflectionObject->hasProperty($item)
            && !($prop = $reflectionObject->getProperty($item))->isPublic()) {
                $className = $prop->getDeclaringClass()->getName();
                $keyItem = "$item:$className";
                $keyItem = $prop->isPrivate() ? "$keyItem:private" : (
                    $prop->isProtected() ? "$keyItem:protected" : $item
                );
            }
            $info[$keyItem] = $value;
            if ($detectParent && in_array($item, $excludeKeys)) {
                continue;
            }
            if (is_object($value)) {
                $info[$keyItem] = sprintf(
                    'object[%s](%s)',
                    spl_object_hash($value),
                    $value::class
                );
                continue;
            }
            $contains = $detectParent && (in_array($item, $keyRedacted)
                    || (is_string($item)
                        && $regexP
                        && preg_match($regexP, $item)
                    ));
            if (!is_array($value)) {
                if (!$contains) {
                    continue;
                }
                $info[$keyItem] = sprintf('(%s : <redacted>)', gettype($value));
                continue;
            }

            foreach ($value as $key => $v) {
                if ($contains) {
                    $info[$keyItem][$key] = sprintf('(%s : <redacted>)', gettype($v));
                    continue;
                }
                if (!$detectSubKey) {
                    if (is_object($v)) {
                        $info[$keyItem][$key] = sprintf(
                            'object[%s](%s)',
                            spl_object_hash($v),
                            $v::class
                        );
                    }
                    continue;
                }
                if (in_array($key, $keyRedacted)
                    || (is_string($key)
                        && $regexP
                        && preg_match($regexP, $key)
                    )) {
                    $info[$keyItem][$key] = sprintf('(%s : <redacted>)', gettype($v));
                }
            }
        }

        return $info;
    }

    public static function baseURL(
        ContainerInterface $container,
        RequestInterface|UriInterface $uri,
        string $path = ''
    ): string {
        return (string) self::baseURI($container, $uri, $path)
            ->withQuery('')
            ->withFragment('');
    }

    public static function baseURI(
        ContainerInterface $container,
        RequestInterface|UriInterface $uri,
        string $path = ''
    ): UriInterface {
        if ($uri instanceof RequestInterface) {
            $uri = $uri->getUri();
        }
        $basePath = ContainerHelper::use(
            RouterInterface::class,
            $container
        )->getBasePath();
        $basePath = ($basePath[0]??'') === '' ? "/$basePath" : $basePath;
        $containSlash = str_ends_with($basePath, '/');
        if (!$containSlash && ($path === '' || !preg_match('~[#?/:]~', $path))) {
            $path = "/$path";
        } elseif ($containSlash && str_starts_with($path, '/')) {
            $basePath = substr($basePath, 0, -1);
        }

        return $uri->withPath($basePath . $path);
    }

    /**
     * @param $data
     * @param bool $print
     * @return object
     * @internal
     */
    public static function debugPrint($data, bool $print = true): object
    {
        $result = new class($data) extends Consolidation {
            private string $type;
            private array $debug;

            public function __construct($data)
            {
                $this->type = gettype($data);
                $this->debug = $this->createNested($data);
            }

            private function createNested($data, int $depth = 0)
            {
                if (is_object($data)) {
                    return [
                        'clasName' => $data::class,
                        'hash' => spl_object_hash($data),
                        'object' => (new ReflectionObject($data))->isInternal() ? $data : (function ($obj, $depth) {
                            $data = [];
                            $ob = function ($data, $depth) {
                                return $this->createNested($data, $depth);
                            };
                            foreach (get_object_vars($this) as $prop => $v) {
                                if (!is_object($v) || $depth === 0) {
                                    $data[$prop] = $ob->call($obj, $v, $depth+1);
                                    continue;
                                }
                                $data[$prop] = Consolidation::debugInfo($v);
                            }
                            return $data;
                        })->call($data, $this, $depth)
                    ];
                }
                if (is_iterable($data)) {
                    foreach ($data as $key => $item) {
                        $data[$key] = $this->createNested($item);
                    }
                    return $data;
                }
                if (is_string($data)
                    && ($length = strlen($data)) > 1024
                ) {
                    return sprintf(
                        '(string: %d)%s',
                        $length,
                        substr($data, 0, 1024)
                    );
                }
                return $data;
            }

            public function __debugInfo(): ?array
            {
                return [
                    'type' => $this->type,
                    'data' => $this->debug
                ];
            }
        };
        if ($print) {
            print_r($result);
        }
        return $result;
    }

    /*! AUTOLOADER */
    /**
     * @var array<string, array<string, callable>>
     */
    private static array $registeredLoaderAutoloader = [];

    private static array $registeredDirectoriesAutoloader = [];

    /**
     * Register autoloader with namespace
     *
     * @param string $namespace
     * @param string $directory
     * @return bool
     */
    public static function deRegisterAutoloader(
        string $namespace,
        string $directory
    ): bool {
        $namespace = trim($namespace, '\\');
        $namespace = $namespace . '\\';
        $directory = realpath($directory)?:$directory;
        if (!isset(self::$registeredLoaderAutoloader[$namespace][$directory])) {
            return false;
        }
        $callback = self::$registeredLoaderAutoloader[$namespace][$directory];
        unset(self::$registeredLoaderAutoloader[$namespace][$directory]);
        if (!is_callable($callback)) {
            return false;
        }
        return spl_autoload_unregister($callback);
    }

    /**
     * Register autoloader with namespace
     *
     * @param string $namespace
     * @param string $directory
     * @param bool $prepend
     * @return bool
     */
    public static function registerAutoloader(
        string $namespace,
        string $directory,
        bool $prepend = false
    ): bool {
        static $include = null;
        $include ??= Closure::bind(static function ($file) {
            include_once $file;
        }, null, null);
        $namespace = trim($namespace, '\\');
        if (!$namespace
            || !is_dir($directory)
            || !Consolidation::isValidClassName($namespace)
        ) {
            return false;
        }

        $namespace = $namespace . '\\';
        $directory = realpath($directory)?:$directory;
        if (!empty(self::$registeredLoaderAutoloader[$namespace][$directory])) {
            return false;
        }

        self::$registeredLoaderAutoloader[$namespace][$directory] = static function (
            $className
        ) use (
            $namespace,
            $directory,
            $include
        ) {
            if (!str_starts_with($className, $namespace)) {
                return;
            }
            $file = substr($className, strlen($namespace));
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $file);
            $fileName = $directory .  DIRECTORY_SEPARATOR . $file. ".php";
            if (isset(self::$registeredDirectoriesAutoloader[$fileName])) {
                return;
            }
            self::$registeredDirectoriesAutoloader[$fileName] = true;
            if (file_exists($fileName)) {
                $include($fileName);
            }
        };

        if (!spl_autoload_register(
            self::$registeredLoaderAutoloader[$namespace][$directory],
            true,
            $prepend
        )) {
            unset(self::$registeredLoaderAutoloader[$namespace][$directory]);
        }

        return is_string(self::$registeredLoaderAutoloader[$namespace][$directory]);
    }

    /**
     * @param $data
     * @param string|null $root
     * @return mixed
     */
    public static function protectMessage($data, ?string $root = null) : mixed
    {
        if (is_numeric($data)) {
            return $data;
        }

        if (is_string($data) || $data instanceof Stringable) {
            $root ??= PossibleRoot::getPossibleRootDirectory();
            $root = DataNormalizer::normalizeDirectorySeparator($root, true);
            $root = preg_quote($root . DIRECTORY_SEPARATOR, '~');
            $data = preg_replace("~$root~", '', (string) $data);
            if (str_contains($data, 'SQLSTATE')) {
                $data = preg_replace(
                    '~(SQLSTATE.+)\'[^\'@]+\'@\'[^\']+\'~',
                    "$1<redacted:user>@<redacted:host>",
                    $data
                );
            }
            return $data;
        }
        if (is_iterable($data)) {
            $root ??= PossibleRoot::getPossibleRootDirectory();
            foreach ($data as $key => $v) {
                $data[$key] = self::protectMessage($v, $root);
            }
        }

        return $data;
    }
}
