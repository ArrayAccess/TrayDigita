<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator;

use Stringable;
use Throwable;
use function chr;
use function function_exists;
use function is_bool;
use function mt_rand;
use function openssl_random_pseudo_bytes;
use function rand;
use function random_bytes;
use function strlen;

class RandomString implements Stringable
{
    const HEX = 'abcdef0123456789';
    const ALPHA_NUMERIC = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const SPECIAL_CHAR = '~`!@#$%^&*()_-+={[}]|\:;"\'<,>.?/';
    const DEFAULT_CHAR = self::ALPHA_NUMERIC . self::SPECIAL_CHAR;

    /**
     * @param int $length
     * @param string|null $char
     * @return string
     */
    public static function char(int $length = 64, ?string $char = self::DEFAULT_CHAR) : string
    {
        if ($length < 1) {
            return '';
        }

        $chars = $char?:self::DEFAULT_CHAR;
        $charactersLength = strlen($chars);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $chars[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param int $bytes
     * @return string
     */
    public static function bytes(int $bytes) : string
    {
        static $pseudo = null;

        if ($bytes < 1) {
            return '';
        }
        try {
            return random_bytes($bytes);
        } catch (Throwable) {
            if (!is_bool($pseudo)) {
                $pseudo = function_exists('openssl_random_pseudo_bytes');
            }
            try {
                if ($pseudo) {
                    return openssl_random_pseudo_bytes($bytes);
                }
            } catch (Throwable) {
                // pass
            }
            $random = '';
            while (strlen($random) < $bytes) {
                $random .= chr(mt_rand(0, 255));
            }
            return $random;
        }
    }

    public static function randomHex(int $length) : string
    {
        return self::char($length, self::HEX);
    }

    public function __toString() : string
    {
        return self::char();
    }
}
