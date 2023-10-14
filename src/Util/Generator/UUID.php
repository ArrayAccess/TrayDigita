<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator;

use Stringable;
use function chr;
use function hexdec;
use function md5;
use function mt_rand;
use function preg_match;
use function sha1;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use const CASE_LOWER;
use const CASE_UPPER;

class UUID implements Stringable
{
    const V5 = 5;
    const V4 = 4;
    const V3 = 3;

    /**
     * @param string $uuid
     * @param int|null $case CASE_LOWER|CASE_UPPER
     * @return bool|int
     */
    public static function validate(
        string $uuid,
        int $case = null
    ) : bool|int {
        if (trim($uuid) === '') {
            return false;
        }
        $regex = '~^[0-9A-F]{8}-[0-9A-F]{4}-([345])[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$~';
        $case === null && $regex .= 'i';
        if ($case === CASE_LOWER) {
            $regex = strtolower($regex);
        }

        // 1070fd92-61ad-47bc-b749-613d6fd0b30d
        preg_match($regex, $uuid, $match);
        if (empty($match)) {
            return false;
        }
        return (int) $match[1];
    }

    /**
     * @param string $namespace
     *
     * @return ?string
     */
    private static function convertNamespace(string $namespace) : ?string
    {
        if (!self::validate($namespace)) {
            return null;
        }
        // Get hexadecimal components of namespace
        $nHex = str_replace(['-','{','}'], '', $namespace);

        // Binary Value
        $nStr = '';
        // Convert Namespace UUID to bits
        for ($i = 0; $i < strlen($nHex); $i+=2) {
            $nStr .= chr(hexdec($nHex[$i].$nHex[$i+1]));
        }
        return $nStr;
    }

    /**
     * Generate v3 UUID
     *
     * Version 3 UUIDs are named based. They require a namespace (another
     * valid UUID) and a value (the name). Given the same namespace and
     * name, the output is always the same.
     *
     * @param string $namespace
     * @param string $name
     * @return bool|string
     */
    public static function v3(string $namespace, string $name) : bool|string
    {
        $nStr = self::convertNamespace($namespace);
        if (!$nStr) {
            return false;
        }
        // Calculate hash value
        $hash = md5($nStr . $name);

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            // 32 bits for "time_low"
            substr($hash, 0, 8),
            // 16 bits for "time_mid"
            substr($hash, 8, 4),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 3
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            // 48 bits for "node"
            substr($hash, 20, 12)
        );
    }

    /**
     * Generate UUID
     *
     * @param int $case use CASE_LOWER|CASE_UPPER
     * @return string
     */
    public static function v4(int $case = CASE_LOWER) : string
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
        return $case === CASE_UPPER ? strtoupper($uuid) : $uuid;
    }

    /**
     * @param string $namespace
     * @param string $name
     *
     * @return bool|string
     */
    public static function v5(string $namespace, string $name) : bool|string
    {
        $nStr = self::convertNamespace($namespace);
        if (!$nStr) {
            return false;
        }

        // Calculate hash value
        $hash = sha1($nStr . $name);
        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            // 32 bits for "time_low"
            substr($hash, 0, 8),
            // 16 bits for "time_mid"
            substr($hash, 8, 4),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 5
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            // 48 bits for "node"
            substr($hash, 20, 12)
        );
    }

    /**
     * Fallback Default
     *
     * @return string
     */
    public function __toString() : string
    {
        return self::v4();
    }
}
