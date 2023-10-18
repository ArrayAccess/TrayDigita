<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use function error_clear_last;
use function is_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function preg_match;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function str_contains;
use function strlen;
use function strpos;
use function substr;
use function trim;
use function unserialize;
use const PREG_NO_ERROR;

class DataType
{
    /**
     * @param $response
     *
     * @return bool
     */
    public static function isJsonContentType(
        $response
    ): bool {
        $contentType = $response instanceof ResponseInterface
            ? $response->getHeaderLine('Content-Type')
            : $response;
        if (!is_string($contentType)) {
            return false;
        }

        return (bool)preg_match(
            '~^(application|text)/(?:vnd\.api+)?json(?:[;\s]|$)~i',
            trim($contentType)
        );
    }

    /**
     * @param $response
     *
     * @return bool
     */
    public static function isHtmlContentType(
        $response
    ): bool {
        $contentType = $response instanceof ResponseInterface
            ? $response->getHeaderLine('Content-Type')
            : $response;
        if (!is_string($contentType)) {
            return false;
        }

        return (bool)preg_match(
            '~^text/x?html(?:[;\s]|$)~i',
            trim($contentType)
        );
    }

    /**
     * Check if valid regex
     *
     * @param string $regexP
     *
     * @return bool
     */
    public static function isValidRegExP(string $regexP): bool
    {
        set_error_handler(static function () {
            error_clear_last();
        });
        $result = preg_match($regexP, '', flags: PREG_NO_ERROR) !== false;
        restore_error_handler();

        return $result;
    }

    /**
     * Check if binary string
     *
     * @param string $str
     *
     * @return bool
     */
    public static function isBinary(string $str): bool
    {
        return (bool)preg_match('~[^\x20-\x7E\t\r\n]~', $str);
    }

    /**
     * Check if hex string
     *
     * @param string $str
     *
     * @return bool
     */
    public static function isHex(string $str): bool
    {
        return !preg_match('~[^a-fA-F0-9]~', $str);
    }

    /**
     * Check if hex lowercase string
     *
     * @param string $str
     *
     * @return bool
     */
    public static function isLowerHex(string $str): bool
    {
        return !preg_match('~[^a-f0-9]~', $str);
    }

    /**
     * Check if hex uppercase string
     *
     * @param string $str
     *
     * @return bool
     */
    public static function isUpperHex(string $str): bool
    {
        return !preg_match('~[^A-F0-9]~', $str);
    }

    public static function isNumberOnly($str) : bool
    {
        return is_numeric($str) && (is_int($str) || !str_contains((string) $str, '.'));
    }

    /**
     * Check if string is base64 encoded
     *
     * @param string $str
     *
     * @return bool
     */
    public static function isBase64(string $str): bool
    {
        return (bool)preg_match('~^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$~', $str);
    }

    /* --------------------------------------------------------------------------------*
     |                              Serialize Helper                                   |
     |                                                                                 |
     | Custom From WordPress Core wp-includes/functions.php                            |
     |---------------------------------------------------------------------------------|
     */

    /**
     * Check value to find if it was serialized.
     * If $data is not a string, then returned value will always be false.
     * Serialized data is always a string.
     *
     * @param mixed $data Value to check to see if was serialized.
     * @param bool $strict Optional. Whether to be strict about the end of the string. Defaults true.
     *
     * @return bool  false if not serialized and true if it was.
     */
    public static function isSerialized(mixed $data, bool $strict = true): bool
    {
        /* if it isn't a string, it isn't serialized
         ------------------------------------------- */
        if (!is_string($data) || trim($data) === '') {
            return false;
        }

        $data = trim($data);
        // null && boolean
        if ('N;' === $data || $data === 'b:0;' || 'b:1;' === $data) {
            return true;
        }

        if (strlen($data) < 4 || ':' !== $data[1]) {
            return false;
        }

        if ($strict) {
            $last_char = substr($data, -1);
            if (';' !== $last_char && '}' !== $last_char) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');

            // Either ; or } must exist.
            if (false === $semicolon && false === $brace
                || false !== $semicolon && $semicolon < 3
                || false !== $brace && $brace < 4
            ) {
                return false;
            }
        }

        $token = $data[0];
        switch ($token) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (!str_contains($data, '"')) {
                    return false;
                }
            // or else fall through
            case 'a':
            case 'O':
            case 'C':
                return (bool)preg_match("/^$token:[0-9]+:/s", $data);
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return (bool)preg_match("/^$token:[0-9.E-]+;$end/", $data);
        }

        return false;
    }

    /**
     * Un-serialize value only if it was serialized.
     *
     * @param string $original Maybe un-serialized original, if is needed.
     *
     * @return mixed  Un-serialized data can be any type.
     */
    public static function shouldUnSerialize(mixed $original): mixed
    {
        if (!is_string($original) || trim($original) === '') {
            return $original;
        }

        /**
         * Check if serialized
         * check with trim
         */
        if (self::isSerialized($original)) {
            try {
                $result = Consolidation::callbackReduceError(
                    static fn() => unserialize(trim($original)),
                    $errNo
                );
                !$errNo && $original = $result;
                unset($result);
            } catch (Throwable) {
            }
        }

        return $original;
    }

    /**
     * Serialize data, if needed. @param mixed $data Data that might be serialized.
     *
     * @param bool $doubleSerialize Double Serialize if you want to use returning real value of serialized
     *                                for database result
     *
     * @return mixed A scalar data
     * @uses for ( un-compress serialize values )
     * This method to use safe as safe data on database. Value that has been
     * Serialized will be double serialize to make sure data is stored as original
     *
     *
     */
    public static function shouldSerialize(mixed $data, bool $doubleSerialize = true): mixed
    {
        /**
         * Double serialization is required for backward compatibility.
         * if @param bool $doubleSerialize is enabled
         */
        if (is_array($data)
            || is_object($data)
            || $doubleSerialize && self::isSerialized($data, false)
        ) {
            return serialize($data);
        }

        return $data;
    }

    /**
     * Append no cache response
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public static function appendNoIndex(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader(
                'X-Robots-Tag',
                'noindex, nofollow, noodp, noydir, noarchive'
            );
    }

    /**
     * Add no cache response
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public static function appendNoCache(ResponseInterface $response) : ResponseInterface
    {
        return $response
            ->withHeader(
                'Pragma',
                'no-cache'
            )->withHeader(
                'Cache-control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
    }
}
