<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Google;

use ArrayAccess\TrayDigita\Util\Generator\RandomString;
use function chr;
use function floor;
use function hash_hmac;
use function http_build_query;
use function is_int;
use function is_numeric;
use function max;
use function min;
use function ord;
use function pack;
use function pow;
use function rawurlencode;
use function rtrim;
use function sprintf;
use function str_pad;
use function str_shuffle;
use function strlen;
use function substr;
use function substr_count;
use function time;
use function trim;
use function unpack;
use const PHP_QUERY_RFC3986;
use const STR_PAD_LEFT;

class Authenticator
{
    /**
     * @var ?int current time
     */
    protected static ?int $time = null;

    /**
     * Default period range seconds
     */
    public const PERIOD = 30;

    /**
     * @param int $time
     * @param int $offset
     * @param int $period
     * @return float|int
     */
    public static function getTimeSlice(
        int $time,
        int $offset = 0,
        int $period = self::PERIOD
    ): float|int {
        return floor($time / $period) + $offset;
    }

    public static function isEqual(string $string1, string $string2): bool
    {
        return substr_count($string1 ^ $string2, "\0") * 2 === strlen($string1 . $string2);
    }

    /**
     * @param string $secret
     * @param string|int $code
     * @param ?int $time
     * @param int $period
     * @return bool
     */
    public static function authenticate(
        string $secret,
        string|int $code,
        ?int $time = null,
        int $period = self::PERIOD
    ): bool {
        self::$time ??= time();
        $time    ??= self::$time;
        $window  = 1;
        $code = is_int($code) ? (string) $code : $code;
        if (!is_numeric($code)) {
            return false;
        }
        $codeLength = strlen($code);
        if ($codeLength !== 6 && $codeLength !== 8) {
            return false;
        }
        $correct = false;
        for ($i = -$window; $i <= $window; $i++) {
            $timeSlice = self::getTimeSlice($time, $i, $period);
            if (self::isEqual(self::calculateCode($secret, $timeSlice, $codeLength, $period), $code)) {
                $correct = true;
                break;
            }
        }

        return $correct;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public static function randomString(
        int $length
    ): string {
        $keyspace = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $str = '';
        for ($i = 0; $i < $length; ++$i) {
            $keyspace = str_shuffle($keyspace);
            $str .= $keyspace[$i];
        }
        return $str;
    }

    public static function generateRandomCode(int $length = 16, string|int|float $prefix = ''): string
    {
        $length += strlen($prefix);
        return substr(
            rtrim(Converter::base32Encode($prefix . RandomString::bytes($length)), '='),
            0,
            $length
        );
    }

    /**
     * @param string $secret
     * @param int|float|null $timeSlice
     * @param int $codeLength
     * @param int $period
     * @return string
     */
    public static function calculateCode(
        string $secret,
        int|float|null $timeSlice = null,
        int $codeLength = 6,
        int $period = self::PERIOD
    ): string {
        $codeLength = $codeLength !== 6 && $codeLength !== 8 ? 6 : 8;
        self::$time ??= time();
        $timeSlice ??= self::getTimeSlice(self::$time, period: $period);
        $timeSlice = pack("N", $timeSlice);
        $timeSlice = str_pad($timeSlice, 8, chr(0), STR_PAD_LEFT);
        $hash = hash_hmac("sha1", $timeSlice, Converter::base32Decode($secret), true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $result = substr($hash, $offset, 4);
        $value = unpack('N', $result)[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, $codeLength);
        return str_pad((string)($value % $modulo), $codeLength, '0', STR_PAD_LEFT);
    }

    public static function createLink(
        string $code,
        string $accountName,
        ?string $issuer = null,
        int $codeLength = 6,
        int $period = self::PERIOD
    ): string {
        $codeLength = $codeLength !== 6 && $codeLength !== 8 ? 6 : 8;
        $period = max($period, 15);
        $period = min($period, 1800);
        $args = [
            'secret' => $code,
            'period' => $period,
            'digits' => $codeLength
        ];
        $host = '';
        if ($issuer && trim($issuer) !== '') {
            $issuer = trim($issuer);
            $args['issuer'] = '';
            $host .= rawurlencode($issuer) .': ';
        }

        $host .= rawurlencode($accountName);
        return sprintf(
            'otpauth://totp/%s?%s',
            $host,
            http_build_query($args, encoding_type: PHP_QUERY_RFC3986)
        );
    }
}
