<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator;

use function bin2hex;
use function chr;
use function func_get_args;
use function gmdate;
use function hash_hmac;
use function hex2bin;
use function implode;
use function is_numeric;
use function mt_rand;
use function ord;
use function password_hash;
use function password_verify;
use function preg_match;
use function round;
use function sprintf;
use function str_split;
use function strlen;
use function strtotime;
use function substr;
use const PASSWORD_DEFAULT;

class RandomToken
{
    public const A_MONTH_IN_SECOND = 18144000;

    public const A_WEEK_IN_SECOND = 604800;

    public const A_DAY_IN_SECOND = 86400;

    public const AN_HOUR_IN_SECOND = 3600;

    public const A_MINUTE_IN_SECOND = 60;

    // 128 BytesLength
    public const BYTES_LENGTH = 16;

    /**
     * @var string[]
     */
    protected static array $token = [];

    /**
     * @return int
     */
    public static function getUTCTime() : int
    {
        return strtotime(gmdate('Y-m-d H:i:s'));
    }

    /**
     * @param string $hash
     * @param string $secretKey
     * @return array|null
     */
    public static function parse(string $hash, string $secretKey) : ?array
    {
        if (preg_match('~[^a-f0-9]~', $hash)) {
            return null;
        }
        $hash = str_split(hex2bin($hash), 2);
        $allTime = '';
        foreach ($hash as &$value) {
            if (strlen($allTime) < 60) {
                $allTime .= $value[1];
                $value    = $value[0];
            }
        }
        if (strlen($allTime) !== 60 || !is_numeric($allTime)) {
            return null;
        }

        $expireRandom   = '';
        $startRandom    = '';
        $startEndTime = str_split($allTime, 3);
        foreach ($startEndTime as &$start) {
            if (strlen($start) !== 3) {
                break;
            }
            $startRandom   .= $start[0];
            $expireRandom .= $start[2];
            $start       = $start[1];
        }
        $expireRandom = str_split($expireRandom, 10);
        $startRandom  = str_split($startRandom, 10);
        $startEndTime  = str_split(implode('', $startEndTime), 10);
        if ($expireRandom[0] !== $startRandom[1]
            || $expireRandom[1] !== $startRandom[0]
        ) {
            return null;
        }

        $hash  = implode($hash);
        $iv = '';
        $hash = str_split($hash, 4);
        foreach ($hash as $key => &$item) {
            if ($key > 15 || !isset($item[3])) {
                break;
            }
            $iv .= $item[3];
            $item = substr($item, 0, 3);
        }
        if (strlen($iv) !== static::BYTES_LENGTH) {
            return null;
        }
        $hash = implode($hash);
        $hash = str_split($hash, (int) (round(strlen($hash)/6)+1));
        $yPos = '';
        foreach ($hash as $key => &$v) {
            if ($key > 2) {
                break;
            }
            $yPos .= substr($v, -2);
            $v    = substr($v, 0, -2);
        }

        $hash = implode('', $hash);
        if (!is_numeric($yPos)) {
            return null;
        }
        $yStart = null;
        $ordinal = '';
        $round = '';
        unset($v);
        foreach (str_split($yPos) as $v) {
            if ($yStart === null) {
                $yStart = $v;
                continue;
            }
            if (strlen($ordinal) !== 3) {
                $ordinal .= $v;
                continue;
            }
            $round .= $v;
        }
        $ordinal = chr((int) $ordinal);
        if (preg_match('~[^a-z]~i', $ordinal)) {
            return null;
        }
        $hash = sprintf('$%s%s$%s$%s', $yStart, $ordinal, $round, $hash);
        if (strlen($hash) !== 60) {
            return null;
        }

        $secretKey = static::hashKey($secretKey);
        $secretHex = hex2bin(static::hashKey($secretKey.$iv));
        $hashed = $iv
            . $secretHex;
        $hashed .= hex2bin($startRandom[0] . $startRandom[1] . $startEndTime[1] . $startEndTime[0]);
        return [
            'hash'     => $hash,
            'iv'       => $iv,
            'hex'      => $secretHex,
            'password' => $hashed,
            'time'   => [
                'time_creation' => $startEndTime[0],
                'expired_after' => $startEndTime[1],
                'random_start'  => $startRandom[0],
                'random_end'    => $startRandom[1],
            ],
        ];
    }

    /**
     * @param string $token
     * @param string $secretKey
     * @return bool|int|null true if valid, otherwise else,
     *      null if invalid creation, or 0 if expired
     */
    public static function verify(string $token, string $secretKey): bool|int|null
    {
        $parse = static::parse($token, $secretKey);
        if (!$parse) {
            return false;
        }

        $utcTime = self::getUTCTime();
        // null if invalid time creation or created on future
        if ($parse['time']['time_creation'] > $utcTime) {
            return null;
        }
        if ($parse['time']['time_creation'] > $parse['time']['expired_after'] // check expired
            || $parse['time']['time_creation'] !== $parse['time']['expired_after']  // check if just keep
            && $utcTime > $parse['time']['expired_after'] // check if expired
        ) {
            return 0; // 0 if expired
        }
        return password_verify($parse['password'], $parse['hash']);
    }

    /**
     * @param string $privateKey
     * @param int $expiredAfter 0 for never expired default 1 day
     * @return string
     */
    public static function create(string $privateKey, int $expiredAfter = self::A_DAY_IN_SECOND): string
    {
        $secretKey = static::hashKey($privateKey);
        $iv = Random::bytes(static::BYTES_LENGTH);
        $secretHex = hex2bin(static::hashKey($secretKey.$iv));
        $currentTime = self::getUTCTime();
        $randomStart = (string) mt_rand(1000000000, 9999999999);
        $randomEnd = (string) mt_rand(1000000000, 9999999999);
        $expiredAfter = $expiredAfter < 1
            ? $currentTime
            : ($currentTime > $expiredAfter ? $currentTime+$expiredAfter : $currentTime);
        $timesStart = '';
        $timesEnd = '';
        for ($i = 0; strlen($randomStart) > $i; $i++) {
            $timesStart .= $randomStart[$i].substr((string)$currentTime, $i, 1).$randomEnd[$i];
        }
        for ($i = 0; strlen($randomEnd) > $i; $i++) {
            $timesEnd .= $randomEnd[$i].substr((string)$expiredAfter, $i, 1).$randomStart[$i];
        }
        $hashed = $iv . $secretHex;
        $hashed .= hex2bin($randomStart . $randomEnd . $expiredAfter . $currentTime);
        $hash = password_hash(
            $hashed,
            PASSWORD_DEFAULT
        );
        preg_match('~^\$([0-9]+)([^0-9\$]+)\$([0-9]+)\$([^$]+)$~', $hash, $match);
        $data = $match[4];
        $saltCombine   = $match[1] . ord($match[2]) . $match[3];
        $data = str_split($data, (int) round(strlen($data)/6));
        $saltCombine   = str_split($saltCombine, strlen($saltCombine)/ 3);
        foreach ($data as $key => &$datum) {
            if (!isset($saltCombine[$key])) {
                break;
            }
            $datum.= $saltCombine[$key];
        }
        $data = implode($data);
        $data = str_split($data, 3);
        foreach ($data as $key => &$item) {
            if (!isset($iv[$key])) {
                break;
            }
            $item .= $iv[$key];
        }
        $data = implode('', $data);
        $timeStartEnd = $timesStart . $timesEnd;
        $hash = '';
        for ($i = 0; strlen($data) > $i; $i++) {
            $hash .= $data[$i];
            if (isset($timeStartEnd[$i])) {
                $hash .= $timeStartEnd[$i];
            }
        }

        return bin2hex($hash);
    }

    /**
     * Has The key what ever he was added the secret key
     *
     * @param string $key
     * @return string
     */
    public static function hashKey(string $key) : string
    {
        // use double key
        return hash_hmac('sha256', $key, $key);
    }

    /**
     * Get Token
     * @param string $key
     * @param int $expired
     * @return string
     */
    public static function lastOrCreate(string $key, int $expired = self::A_DAY_IN_SECOND) : string
    {
        return static::$token[$key]??static::create(...func_get_args());
    }
}
