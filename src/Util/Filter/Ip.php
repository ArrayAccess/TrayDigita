<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use function bin2hex;
use function bindec;
use function dechex;
use function explode;
use function hexdec;
use function inet_ntop;
use function inet_pton;
use function ip2long;
use function is_numeric;
use function is_string;
use function long2ip;
use function min;
use function pow;
use function preg_match;
use function str_contains;
use function strlen;
use function strrpos;
use function substr;
use function substr_count;
use function substr_replace;
use function trim;

/**
 * Filter IP
 */
class Ip
{
    /**
     * IP version 4
     */
    public const IP4 = 4;

    /**
     * IP version 6
     */
    public const IP6 = 6;

    /**
     * Regex for matching local IPv4 addresses
     */
    public const IPV4_LOCAL_REGEX = '~^
        (?:
            (?:0?[01]?0|127|255)\.(?:[01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])
            | 192\.168
            | 172\.16
        )
        (?:\.(?:[01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])){2}
    $~x';

    /**
     * Filters an IPv4 address
     *
     * @param string $ip
     * @return ?string Returns the filtered IP address, or null if the IP is invalid
     */
    public static function filterIpv4(string $ip): ?string
    {
        if (preg_match('/^([01]{8}\.){3}[01]{8}\z/i', $ip)) {
            // binary format 00000000.00000000.00000000.00000000
            $ip = bindec(substr($ip, 0, 8))
                . '.'
                . bindec(substr($ip, 9, 8))
                . '.'
                . bindec(substr($ip, 18, 8))
                . '.'
                . bindec(substr($ip, 27, 8));
        } elseif (preg_match('/^([0-9]{3}\.){3}[0-9]{3}\z/i', $ip)) {
            // octet format 777.777.777.777
            $ip = (int) substr($ip, 0, 3) . '.' . (int) substr($ip, 4, 3) . '.'
                . (int) substr($ip, 8, 3) . '.' . (int) substr($ip, 12, 3);
        } elseif (preg_match('/^([0-9a-f]{2}\.){3}[0-9a-f]{2}\z/i', $ip)) {
            // hex format ff.ff.ff.ff
            $ip = hexdec(substr($ip, 0, 2)) . '.' . hexdec(substr($ip, 3, 2)) . '.'
                . hexdec(substr($ip, 6, 2)) . '.' . hexdec(substr($ip, 9, 2));
        }
        if (($ip2long = ip2long($ip)) === false) {
            return null;
        }

        return $ip === long2ip($ip2long) ? $ip : null;
    }

    /**
     * Validates an IPv4 address
     *
     * @param string $ip
     * @return bool True when $ip is a valid ipv4 address
     */
    public static function isValidIpv4(string $ip): bool
    {
        return self::filterIpv4($ip) !== false;
    }

    /**
     * Validates an IPv4 address
     *
     * @param string $ip
     * @return bool True when $ip is a valid local ipv4 address
     */
    public static function isLocalIP4(string $ip): bool
    {
        $ip = self::filterIpv4($ip);
        return $ip && preg_match(self::IPV4_LOCAL_REGEX, $ip);
    }

    /**
     * Validates an IPv6 address
     *
     * @param  string $value Value to check against
     * @return bool True when $value is a valid ipv6 address
     *                 False otherwise
     */
    public static function isValidIpv6(string $value): bool
    {
        if (strlen($value) < 3) {
            return $value === '::';
        }

        if (str_contains($value, '.')) {
            $last_colon = strrpos($value, ':');
            if (! ($last_colon && self::isValidIpv4(substr($value, $last_colon + 1)))) {
                return false;
            }

            $value = substr($value, 0, $last_colon) . ':0:0';
        }

        if (str_contains($value, '::') === false) {
            return (bool) preg_match('/\A(?:[a-f0-9]{1,4}:){7}[a-f0-9]{1,4}\z/i', $value);
        }

        $colonCount = substr_count($value, ':');
        if ($colonCount < 8) {
            return (bool) preg_match('/\A(?::|(?:[a-f0-9]{1,4}:)+):(?:(?:[a-f0-9]{1,4}:)*[a-f0-9]{1,4})?\z/i', $value);
        }

        // special case with ending or starting double colon
        if ($colonCount === 8) {
            return (bool) preg_match('/\A(?:::)?(?:[a-f0-9]{1,4}:){6}[a-f0-9]{1,4}(?:::)?\z/i', $value);
        }

        return false;
    }

    /**
     * Convert ipv4 cidr to range
     *
     * @param string $cidr eg 127.0.0.1/24
     * @return ?array{0: string, 1: string} start & end ip address
     */
    public static function ipv4CIDRToRange(string $cidr): ?array
    {
        if (count(($cidr = explode('/', $cidr))) !== 2) {
            return null;
        }
        if (($ip  = trim($cidr[0])) === ''
            || ($range = trim($cidr[1])) === ''
            || str_contains($range, '.')
            || !is_numeric($range)
            || $range > 32
            || $range < 0
            || !self::isValidIpv4($ip)
        ) {
            return null;
        }
        $ips = explode('.', $ip);
        if (count($ips) !== 4) {
            return null;
        }
        foreach ($ips as $ip_address) {
            if ($ip_address === '') {
                return null;
            }
            if (str_contains('.', $ip_address)
                || ! is_numeric($ip_address)
                || $ip_address > 255
                || $ip_address < 0
            ) {
                return null;
            }
        }
        $range = (int) $range;
        return [
            long2ip((ip2long($ip)) & ((-1 << (32 - $range)))),
            long2ip((ip2long($ip)) + pow(2, (32 - $range)) - 1)
        ];
    }

    /**
     * Convert ipv6 cidr to range
     *
     * @param string $cidr 2001:100::/24
     * @return ?array{0: string, 1: string} start & end ip address
     */
    public static function ipv6CIDRToRange(string $cidr) : ?array
    {
        if (count(($cidr = explode('/', trim($cidr)))) !== 2) {
            return null;
        }
        if (($ip= trim($cidr[0])) === ''
            || ($range = trim($cidr[1])) === ''
            || str_contains($range, '.')
            || !is_numeric($range)
            || $range < 0
            || $range > 128
            || !self::isValidIpv6($ip)
        ) {
            return null;
        }

        $firstAddrBin = inet_pton($ip);
        // fail return null
        if ($firstAddrBin === false
            || !($firstAddr = inet_ntop($firstAddrBin))
        ) {
            return null;
        }
        $flexBits = 128 - ((int) $range);
        // Build the hexadecimal string of the last address
        $lastAddrHex = bin2hex($firstAddrBin);
        // start at the end of the string (which is always 32 characters long)
        $pos = 31;
        while ($flexBits > 0) {
            // Get the character at this position
            $orig = substr($lastAddrHex, $pos, 1);
            // Convert it to an integer
            $originalVal = hexdec($orig);
            // OR it with (2^flexBits)-1, with flexBits limited to 4 at a time
            $newVal = $originalVal | (pow(2, min(4, $flexBits)) - 1);
            // Convert it back to a hexadecimal character
            $new = dechex($newVal);
            // And put that character back in the string
            $lastAddrHex = substr_replace($lastAddrHex, $new, $pos, 1);
            // process one nibble, move to previous position
            $flexBits -= 4;
            $pos -= 1;
        }
        $lastAddrBin = hex2bin($lastAddrHex);
        $lastAddr = inet_ntop($lastAddrBin);
        if (!$lastAddr) {
            return null;
        }
        return [$firstAddr, $lastAddr];
    }

    /**
     * Get IP Version
     *
     * @param string|mixed $ip
     * @return ?int
     */
    public static function version(mixed $ip) : ?int
    {
        if (!is_string($ip)) {
            return null;
        }
        if (str_contains($ip, ':')) {
            return self::isValidIpv6($ip) ? self::IP6 : null;
        }
        return str_contains($ip, '.') && self::isValidIpv4($ip)
            ? self::IP4
            : null;
    }
}
