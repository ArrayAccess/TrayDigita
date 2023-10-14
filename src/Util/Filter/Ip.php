<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use function bindec;
use function explode;
use function hexdec;
use function implode;
use function ip2long;
use function is_numeric;
use function is_string;
use function long2ip;
use function ltrim;
use function pow;
use function preg_match;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;
use function substr_count;
use function trim;

class Ip
{
    const IP4 = 4;
    const IP6 = 6;

    const IPV4_LOCAL_REGEX = '~^
        (?:
            (?:
                1?0 | # start with 0. or 10.
                127  # start with 127.
            )\.(?:0|2(?:[0-4][0-9]?|5[0-5]?|[6-9])?|1[0-9]{0,2}|[1-9][0-9]?) # next 0 to 255
            | 192\.168
            | 172\.16
        )
        # next 0. to 255. twice
        (?:
            \.(?:0|2(?:[0-4][0-9]?|5[0-5]?|[6-9])?|1[0-9]{0,2}|[1-9][0-9]?)
        ){2}
    $~x';

    public static function filterIpv4(string $ip): false|string
    {
        if (preg_match('/^([01]{8}\.){3}[01]{8}\z/i', $ip)) {
            // binary format  00000000.00000000.00000000.00000000
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
        $ip2long = ip2long($ip);
        if ($ip2long === false) {
            return false;
        }

        return $ip === long2ip($ip2long) ? $ip : false;
    }

    /**
     * Validates an IPv4 address
     *
     * @param string $ip
     * @return bool
     */
    public static function isValidIpv4(string $ip): bool
    {
        return self::filterIpv4($ip) !== false;
    }

    public static function isLocalIP(string $ip): bool
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
        $cidr = str_replace(' ', '', $cidr);
        $cidr = explode('/', $cidr);
        if (count($cidr) !== 2) {
            return null;
        }

        $ip  = $cidr[0];
        $range  = $cidr[1];
        if (!is_numeric($range)
            || strlen($range) > 2
            || str_contains($range, '.')
            || self::isValidIpv4($ip)
        ) {
            return null;
        }
        $range = (int) $range;
        if ($range < 1 || $range > 32) {
            return null;
        }
        $ips = explode('.', $ip);
        if (count($ips) !== 4) {
            return null;
        }
        $ip_temp = [];
        foreach ($ips as $ip_address) {
            $ip_address = trim($ip_address);
            if ($ip_address === '') {
                return null;
            }
            $ip_address = ltrim($ip_address, '0');
            if ($ip_address === '') {
                $ip_address = '0';
            }
            if (!is_numeric($ip_address)
                || str_contains('.', $ip_address)
            ) {
                return null;
            }
            $ip_address = (int) $ip_address;
            if ($ip_address < 0 || $ip_address > 255) {
                return null;
            }
            $ip_temp[] = $ip_address;
        }
        $ip = implode('.', $ip_temp);
        return [
            long2ip((ip2long($ip)) & ((-1 << (32 - $range)))),
            long2ip((ip2long($ip)) + pow(2, (32 - $range)) - 1)
        ];
    }

    /**
     * @param string|mixed $ip
     *
     * @return int|false
     */
    public static function version(mixed $ip) : int|false
    {
        return !is_string($ip) ? false : (
        self::isValidIpv4($ip)
            ? self::IP4
            : (self::isValidIpv6($ip) ? self::IP6 : false)
        );
    }
}
