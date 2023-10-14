<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use Throwable;
use Traversable;
use function array_filter;
use function array_map;
use function array_pop;
use function array_unique;
use function array_values;
use function checkdnsrr;
use function explode;
use function filter_var;
use function function_exists;
use function hash;
use function htmlentities;
use function iconv;
use function in_array;
use function ini_get;
use function is_array;
use function is_bool;
use function is_null;
use function is_object;
use function is_string;
use function ltrim;
use function mb_strlen;
use function parse_url;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;
use function str_replace;
use function stripos;
use function strlen;
use function strtolower;
use function trim;
use const ARRAY_FILTER_USE_KEY;
use const FILTER_VALIDATE_EMAIL;

final class StringFilter
{
    /**
     * @var string[]
     */
    protected static array $protocols = [
        'http',
        'https',
        'ftp',
        'ftps',
        'mailto',
        'news',
        'irc',
        'gopher',
        'nntp',
        'feed',
        'telnet',
        'mms',
        'rtsp',
        'sms',
        'svn',
        'tel',
        'fax',
        'xmpp',
        'webcal',
        'urn'
    ];

    /**
     * @param string $string
     * @param bool $slash_zero
     * @return string
     */
    public static function replaceNullString(string $string, bool $slash_zero = true): string
    {
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $string);
        if ($slash_zero) {
            $string = preg_replace('/\\\\+0+/', '', $string);
        }

        return $string;
    }

    /**
     * @param $search
     * @param string $subject
     * @return string|string[]
     */
    public static function deepReplace($search, string $subject): array|string
    {
        $count = 1;
        while ($count) {
            $subject = str_replace($search, '', $subject, $count);
        }
        return $subject;
    }

    /**
     * @param string $url
     * @param mixed|null $protocols
     * @param bool $display
     *
     * @return string
     */
    public static function escapeUrl(
        string $url,
        mixed $protocols = null,
        bool $display = true
    ): string {
        if ('' === trim($url)) {
            return $url;
        }

        $url = str_replace(' ', '%20', ltrim($url));
        $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url);

        if ('' === $url) {
            return $url;
        }

        if (0 !== stripos($url, 'mailto:')) {
            $strip = ['%0d', '%0a', '%0D', '%0A'];
            $url = self::deepReplace($strip, $url);
        }

        $url = str_replace(';//', '://', $url);
        /*
         * If the URL doesn't appear to contain a scheme, we presume
         * it needs http:// prepended (unless it's a relative link
         * starting with /, # or ?, or a PHP file).
         */
        if (!str_contains($url, ':') && !in_array($url[0], ['/', '#', '?']) &&
            !preg_match('/^[a-z0-9-]+?\.php/i', $url)) {
            $url = 'https://' . $url;
        }

        // Replace ampersands and single quotes only when displaying.
        if ($display) {
            $url = htmlentities($url);
            $url = str_replace('&amp;', '&#038;', $url);
            $url = str_replace("'", '&#039;', $url);
        }

        if ((str_contains($url, '[')) || (str_contains($url, ']'))) {
            $parsed = parse_url($url);
            $front = '';

            if (isset($parsed['scheme'])) {
                $front .= $parsed['scheme'] . '://';
            } elseif ('/' === $url[0]) {
                $front .= '//';
            }

            if (isset($parsed['user'])) {
                $front .= $parsed['user'];
            }

            if (isset($parsed['pass'])) {
                $front .= ':' . $parsed['pass'];
            }

            if (isset($parsed['user']) || isset($parsed['pass'])) {
                $front .= '@';
            }

            if (isset($parsed['host'])) {
                $front .= $parsed['host'];
            }

            if (isset($parsed['port'])) {
                $front .= ':' . $parsed['port'];
            }

            $end_dirty = str_replace($front, '', $url);
            $end_clean = str_replace(['[', ']'], ['%5B', '%5D'], $end_dirty);
            $url = str_replace($end_dirty, $end_clean, $url);
        }
        if ('/' === $url[0]) {
            $good_protocol_url = $url;
        } else {
            if (!is_array($protocols)) {
                $protocols = self::$protocols;
            }

            preg_match('#^([^:]+):(/+)?([^/].+)#', $url, $match);
            $protocol = $match[1] ?? null;
            $uri = $match[3] ?? null;
            if (!$protocol || !in_array($protocol, $protocols)) {
                return '';
            }

            $good_protocol_url = sprintf('%s://%s', $protocol, $uri);
            if (strtolower($good_protocol_url) !== strtolower($url)) {
                return '';
            }
        }

        /**
         * Filters a string cleaned and escaped for output as a URL.
         *
         * @param string $good_protocol_url The cleaned URL to be returned.
         * @param string $original_url The URL prior to cleaning.
         * @param string $display If 'display', replace ampersands and single quotes only.
         */
        return $good_protocol_url;
    }

    /**
     * @param bool $reset
     */
    public static function mbStringBinarySafeEncoding(bool $reset = false): void
    {
        static $encodings = [];
        static $overloaded = null;

        if (is_null($overloaded)) {
            $overloaded = function_exists('mb_internal_encoding')
                && (ini_get('mbstring.func_overload') & 2);
        }

        if (false === $overloaded) {
            return;
        }

        if (!$reset) {
            $encoding = mb_internal_encoding();
            $encodings[] = $encoding;
            mb_internal_encoding('ISO-8859-1');
        }

        if ($reset && $encodings) {
            $encoding = array_pop($encodings);
            mb_internal_encoding($encoding);
        }
    }

    public static function resetMbStringEncoding(): void
    {
        self::mbStringBinarySafeEncoding(true);
    }

    /**
     * Check if data is (or contains) Binary
     *
     * @param string $str
     * @return bool
     */
    public static function isBinary(string $str): bool
    {
        return preg_match('~[^\x20-\x7E]~', $str) > 0;
    }

    /**
     * Check if data is Base 64
     *
     * @param string $str
     * @return bool
     */
    public static function isBase64(string $str): bool
    {
        return preg_match(
            '~^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$~',
            $str
        ) > 0;
    }

    /**
     * check if data is https? url
     *
     * @param string $str
     * @return bool
     */
    public static function isHttpUrl(string $str): bool
    {
        return preg_match('~^https?://[^.]+\.(.+)$~i', $str) > 0;
    }

    /**
     * Sanitize non utf-8 string
     * @param string $data
     * @return string
     */
    public static function sanitizeUtf8Encode(string $data): string
    {
        static $utf8;
        if (!is_bool($utf8)) {
            $utf8 = function_exists('utf8_encode');
        }
        if (!$utf8) {
            return $data;
        }

        $regex = '/(
            [\xC0-\xC1] # Invalid UTF-8 Bytes
            | [\xF5-\xFF] # Invalid UTF-8 Bytes
            | \xE0[\x80-\x9F] # Overlong encoding of prior code point
            | \xF0[\x80-\x8F] # Overlong encoding of prior code point
            | [\xC2-\xDF](?![\x80-\xBF]) # Invalid UTF-8 Sequence Start
            | [\xE0-\xEF](?![\x80-\xBF]{2}) # Invalid UTF-8 Sequence Start
            | [\xF0-\xF4](?![\x80-\xBF]{3}) # Invalid UTF-8 Sequence Start
            | (?<=[\x00-\x7F\xF5-\xFF])[\x80-\xBF] # Invalid UTF-8 Sequence Middle
            | (?<![\xC2-\xDF]
                |[\xE0-\xEF]
                |[\xE0-\xEF][\x80-\xBF]
                |[\xF0-\xF4]
                |[\xF0-\xF4][\x80-\xBF]
                |[\xF0-\xF4][\x80-\xBF]{2}
                )[\x80-\xBF] # Overlong Sequence
            | (?<=[\xE0-\xEF])[\x80-\xBF](?![\x80-\xBF]) # Short 3 byte sequence
            | (?<=[\xF0-\xF4])[\x80-\xBF](?![\x80-\xBF]{2}) # Short 4 byte sequence
            | (?<=[\xF0-\xF4][\x80-\xBF])[\x80-\xBF](?![\x80-\xBF]) # Short 4 byte sequence (2)
        )/x';
        return preg_replace_callback($regex, function ($e) {
            return utf8_encode($e[1]);
        }, $data);
    }

    /**
     * Sanitize Result to UTF-8 , this is recommended to sanitize
     * that result from socket that invalid decode UTF8 values
     *
     * @param string $string
     *
     * @return string
     */
    public static function sanitizeInvalidUtf8FromString(string $string): string
    {
        static $iconv = null;
        if (!is_bool($iconv)) {
            $iconv = function_exists('iconv');
        }
        if (!$iconv) {
            return self::sanitizeUtf8Encode($string);
        }

        if (!function_exists('mb_strlen') || mb_strlen($string, 'UTF-8') !== strlen($string)) {
            $result = false;
            // try to un-serial
            try {
                // add temporary error handler
                set_error_handler(function ($errNo, $errStr) {
                    throw new RuntimeException(
                        $errStr,
                        $errNo
                    );
                });
                /**
                 * use trim if possible
                 * Serialized value could not start & end with white space
                 */
                /** @noinspection PhpComposerExtensionStubsInspection */
                $result = iconv('windows-1250', 'UTF-8//IGNORE', $string);
            } catch (Throwable) {
                // pass
            } finally {
                restore_error_handler();
            }
            if ($result !== false) {
                return self::sanitizeUtf8Encode($string);
            }
        }

        return self::sanitizeUtf8Encode($string);
    }

    /**
     * Sanitize Result to UTF-8 , this is recommended to sanitize
     * that result from socket that invalid decode UTF8 values
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitizeInvalidUtf8(mixed $data): mixed
    {
        if (is_string($data)) {
            return self::sanitizeInvalidUtf8FromString($data);
        }

        if (is_array($data) || $data instanceof Traversable) {
            foreach ($data as $key => $item) {
                $data[$key] = self::sanitizeInvalidUtf8($item);
            }
            return $data;
        }
        if (is_object($data)) {
            // $realData = $data;
            try {
                foreach ($data as $key => $item) {
                    $data->$key = self::sanitizeInvalidUtf8($item);
                }
            } catch (Throwable) {
            }
        }

        return $data;
    }

    /**
     * @param string $email
     * @param bool $validateDNSSR
     * @return string|false
     */
    public static function filterEmailCommon(
        string $email,
        bool $validateDNSSR = false
    ): bool|string {
        $email = trim(strtolower($email));
        $explode = explode('@', $email);
        // validate email address & domain
        if (count($explode) !== 2
            // Domain must be contained Period, and it will be real email
            || !str_contains($explode[1], '.')
            // could not use email with double period and hyphens
            || preg_match('~[.]{2,}|[\-_]{3,}~', $explode[0])
            // check validate email
            || !preg_match('~^[a-zA-Z0-9]+(?:[a-zA-Z0-9._\-]?[a-zA-Z0-9]+)?$~', $explode[0])
        ) {
            return false;
        }

        // filtering Email Address
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // if validate DNS
        if ($validateDNSSR === true && !@checkdnsrr($explode[0])) {
            return false;
        }

        return $email;
    }
    /**
     * @param string $data
     * @return array
     */
    public static function parseImageFromString(string $data) : array
    {
        preg_match_all(
            '/<img.*?src=\s*[\'\"](?P<images>[^\"\']+)/smix',
            $data,
            $match
        );
        $data = [];
        if (!empty($match)) {
            $match = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            $match = isset($match['images'])
                ? array_filter(array_unique($match['images']))
                : [];
            $match = array_map(function ($data) {
                if (!preg_match('/(?:\.png|jpe?g|bmp|tiff|svg)?(?:\?(?:.+)?)?$/i', $data)) {
                    return false;
                }
                return $data;
            }, array_values($match));
            $data = array_filter($match);
        }

        return $data;
    }

    public static function sha256(string $string): string
    {
        return hash('sha256', $string);
    }

    public static function sha512(string $string): string
    {
        return hash('sha512', $string);
    }
}
