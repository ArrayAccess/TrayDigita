<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use DateTime;
use DateTimeInterface;
use Stringable;
use Throwable;
use function chr;
use function dechex;
use function hexdec;
use function implode;
use function md5;
use function microtime;
use function preg_match;
use function sha1;
use function sprintf;
use function str_pad;
use function str_replace;
use function str_split;
use function strlen;
use function substr;
use const STR_PAD_LEFT;

/**
 * UUID class to generate and parse UUID.
 * Only support uuid 1 - 5
 *
 * Usage:
 * - Generate UUID v1: UUID::v1() or UUID::generate(1), UUID::generate(1, UUID::UUID_VARIANT_DCE, UUID::UUID_TYPE_TIME)
 * - Generate UUID v2: UUID::v2() or UUID::generate(2), UUID::generate(2, UUID::UUID_VARIANT_DCE, UUID::UUID_TYPE_TIME)
 * - Generate UUID v3:
 *      UUID::v3(UUID::NAMESPACE_DNS, 'www.example.com')
 *      or UUID::generate(3, UUID::UUID_VARIANT_RFC4122, UUID::UUID_TYPE_MD5, UUID::NAMESPACE_DNS, 'www.example.com')
 * - Generate UUID v4:
 *      UUID::v4()
 *      or UUID::generate(4), UUID::generate(4, UUID::UUID_VARIANT_RFC4122, UUID::UUID_TYPE_RANDOM)
 * - Generate UUID v5:
 *      UUID::v5(UUID::NAMESPACE_DNS, 'www.example.com')
 *      or UUID::generate(5, UUID::UUID_VARIANT_RFC4122, UUID::UUID_TYPE_SHA1, UUID::NAMESPACE_DNS, 'www.example.com')
 * - Parse UUID: UUID::parse('550e8400-e29b-41d4-a716-446655440000')
 * - Get UUID version: UUID::version('550e8400-e29b-41d4-a716-446655440000')
 * - Get UUID integer id: UUID::integerId('550e8400-e29b-41d4-a716-446655440000')
 * - Check if a string is a valid UUID: UUID::isValid('550e8400-e29b-41d4-a716-446655440000')
 * - Extract UUID: UUID::extractUUID('550e8400-e29b-41d4-a716-446655440000')
 * - Extract UUID part: UUID::extractUUIDPart('550e8400-e29b-41d4-a716-446655440000')
 * - Calculate namespace and name:
 *      UUID::calculateNamespaceAndName(UUID::NAMESPACE_DNS, 'www.example.com', UUID::UUID_TYPE_MD5)
 *
 */
class UUID implements Stringable
{
    /* ----------------------------------------------------------------------
     * UUID Types
     * ----------------------------------------------------------------------
     */
    public const UUID_TYPE_TIME = 1;
    public const UUID_TYPE_MD5 = 2;
    public const UUID_TYPE_SHA1 = 3;
    public const UUID_TYPE_RANDOM = 4;

    /* ----------------------------------------------------------------------
     * UUID Variant
     * ----------------------------------------------------------------------
     */
    // NCS backward compatibility (with the obsolete Apollo Network Computing System 1.5 UUID format)
    // is: 0 - 7 (0x0 - 0x7)
    public const UUID_VARIANT_NCS = 0;
    // DCE 1.1, ISO/IEC 11578:1996 is: 128 - 191 (0x80 - 0xbf)
    public const UUID_VARIANT_DCE = 1;
    //  microsoft is 192 - 223 (0xc0 - 0xdf)
    public const UUID_VARIANT_MICROSOFT = 2;
    // reserved for future definition is: 224 - 255 (0xe0 - 0xff)
    public const UUID_VARIANT_RESERVED_FUTURE = 3;
    // RFC 4122, IETF is: 64 - 79 (0x40 - 0x4f)
    public const UUID_VARIANT_RFC4122 = 4;

    /* ----------------------------------------------------------------------
     * UUID namespace constants for UUID::calculateNamespaceAndName()
     * ----------------------------------------------------------------------
     * https://tools.ietf.org/html/rfc4122#appendix-C
     */
    public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

    /* ------------------------------------------------------------------------
     * Variant Name Constants
     * ------------------------------------------------------------------------
     */
    public const DCE_VERSION_NAME = 'DCE 1.1, ISO/IEC 11578:1996';
    public const MICROSOFT_VERSION_NAME = 'Microsoft Corporation GUID';
    public const NCS_VERSION_NAME = 'RESERVED, NCS backward compatibility';
    public const RFC4122_VERSION_NAME = 'RFC 4122, IETF';
    public const RESERVED_FUTURE_VERSION_NAME = 'RESERVED, future definition';

    /**
     * UUID variants for UUID::UUID_VARIANT_* constants
     */
    public const UUID_VARIANTS = [
        self::UUID_VARIANT_NCS => 0x00,
        self::UUID_VARIANT_DCE => 0x80,
        self::UUID_VARIANT_RFC4122 => 0x80,
        self::UUID_VARIANT_MICROSOFT => 0x40,
        self::UUID_VARIANT_RESERVED_FUTURE => 0xe0,
    ];

    /**
     * UUID variant names for UUID::UUID_VARIANT_* constants
     */
    public const UUID_VARIANT_NAMES = [
        self::UUID_VARIANT_NCS => self::NCS_VERSION_NAME,
        self::UUID_VARIANT_DCE => self::DCE_VERSION_NAME,
        self::UUID_VARIANT_MICROSOFT => self::MICROSOFT_VERSION_NAME,
        self::UUID_VARIANT_RFC4122 => self::RFC4122_VERSION_NAME,
        self::UUID_VARIANT_RESERVED_FUTURE => self::RESERVED_FUTURE_VERSION_NAME,
    ];

    /**
     * Single prefix variant for UUID v1
     * The prefix getting from the first character of the 15th character of UUID v1
     */
    public const SINGLE_PREFIX_VARIANT = [
        '0' => self::UUID_VARIANT_NCS,
        '1' => self::UUID_VARIANT_NCS,
        '2' => self::UUID_VARIANT_NCS,
        '3' => self::UUID_VARIANT_NCS,
        '4' => self::UUID_VARIANT_NCS,
        '5' => self::UUID_VARIANT_NCS,
        '6' => self::UUID_VARIANT_NCS,
        '7' => self::UUID_VARIANT_NCS,
        '8' => self::UUID_VARIANT_DCE,
        '9' => self::UUID_VARIANT_DCE,
        'a' => self::UUID_VARIANT_DCE,
        'b' => self::UUID_VARIANT_DCE,
        'c' => self::UUID_VARIANT_MICROSOFT,
        'd' => self::UUID_VARIANT_MICROSOFT,
        'e' => self::UUID_VARIANT_RESERVED_FUTURE,
        'f' => self::UUID_VARIANT_RESERVED_FUTURE,
    ];

    /**
     * Get version from uuid
     *
     * @param string $uuid
     * @return int|null null if not valid uuid
     */
    public static function version(string $uuid): ?int
    {
        // a0eebc99-9c0b-11d1-0000-000000000000
        if (!self::isValid($uuid)) {
            return null;
        }
        $uuidDetails = self::extractUUID($uuid);
        return $uuidDetails['version']??null;
    }

    /**
     * Extract uuid:
     * 1. time low
     * 2. time mid
     * 3. time hi and version
     * 4. clock seq hi and reserved
     * 5. clock seq low
     * 6. node
     * 7. version
     * 8. variant
     * 9. variant name
     *
     * @param string $uuid
     * @return ?array{
     *     time_low: int,
     *     time_mid: int,
     *     time_hi_and_version: int,
     *     clock_seq_hi_and_reserved: int,
     *     clock_seq_low: int,
     *     node: int,
     *     version: int,
     *     variant: int,
     *     variant_name: string,
     * } null if not valid uuid
     */
    public static function extractUUID(string $uuid): ?array
    {
        $matches = self::extractUUIDPart($uuid);
        if (!$matches) {
            return null;
        }
        $timeLow = hexdec($matches[1]);
        $timeMid = hexdec($matches[2]);
        $timeHiAndVersion = hexdec($matches[3]);
        $clockSeqHiAndReserved = hexdec($matches[4]);
        $clockSeqLow = hexdec(substr($matches[4], 2));
        $node = hexdec($matches[5]);
        $variant = substr($matches[4], 0, 1);
        // uuid is hex: 0-9, a-f
        // by default, variant is NCS backward compatibility
        $variant = self::SINGLE_PREFIX_VARIANT[$variant]??self::UUID_VARIANT_NCS;
        $variantName = self::UUID_VARIANT_NAMES[$variant]??self::NCS_VERSION_NAME;
        return [
            'time_low' => $timeLow,
            'time_mid' => $timeMid,
            'time_hi_and_version' => $timeHiAndVersion,
            'clock_seq_hi_and_reserved' => $clockSeqHiAndReserved,
            'clock_seq_low' => $clockSeqLow,
            'node' => $node,
            'version' => $timeHiAndVersion >> 12,
            'variant' => $variant,
            'variant_name' => $variantName,
        ];
    }

    /**
     * Extract uuid part:
     * 1. time low
     * 2. time mid
     * 3. time hi and version
     * 4. clock seq hi and reserved
     * 5. clock seq low
     * 6. node
     *
     * @param string $uuid
     * @return ?array
     */
    public static function extractUUIDPart(string $uuid) : ?array
    {
        preg_match(
            '/^([0-9a-f]{8})-([0-9a-f]{4})-([1-5][0-9a-f]{3})-([0-9a-f]{4})-([0-9a-f]{12})$/i',
            $uuid,
            $matches
        );
        return $matches?:null;
    }

    /**
     * Check if a string is a valid UUID.
     *
     * @param string $uuid
     * @return bool
     */
    public static function isValid(string $uuid): bool
    {
        return self::extractUUIDPart($uuid) !== null;
    }

    /**
     * Get UUID integer id.
     *
     * @param string $uuid UUID to convert
     * @return ?numeric-string unsigned numeric string known as single integer value or null if not valid uuid
     */
    public static function integerId(string $uuid): ?string
    {
        if (!self::isValid($uuid)) {
            return null;
        }

        // remove hyphens
        $hex = str_replace('-', '', $uuid);
        // convert hex to decimal
        $dec = '0';
        // get length of hex
        $length = strlen($hex);
        // loop hex
        // using bcmul() && bcadd() binary calculator function & prevent loss of precision
        for ($i = 0; $i < $length; $i++) {
            // get the char from hex at position $i
            // -> bcmul the decimal by 16
            $dec = Consolidation::multiplyInt($dec, 16);
            // -> bcadd the decimal by the integer value of the hex char
            $dec = Consolidation::addInt($dec, hexdec($hex[$i]));
        }

        return $dec;
    }

    /**
     * Parse the uuid and show the detail of:
     *
     * 1. Single Integer (64 bits) (big endian) from UUID
     * 2. Version
     * 3. Variant
     * 4. Timestamp ISO 8601
     * 6. Node / Contents
     * The contents (and content node for version 1) are per hex string separated by (:)
     * @param string $uuid UUID to parse
     *
     * @return ?array{
     *     uuid: string,
     *     single_integer: string,
     *     version: int,
     *     variant: int,
     *     variant_name: string,
     *     contents_node: string,
     *     contents_time: ?string,
     *     contents_clock: int,
     *     contents: string,
     *     time_low: int,
     *     time_mid: int,
     *     time_hi_and_version: int,
     *     clock_seq_hi_and_reserved: int,
     *     clock_seq_low: int,
     *     node: int,
     * } null if not valid uuid if the version is not 1 the time will be null
     */
    public static function parse(string $uuid) : ?array
    {
        $uuidDetails = self::extractUUID($uuid);
        if (!$uuidDetails) {
            return null;
        }

        if ($uuidDetails['version'] === 1) {
            $timestamp = ($uuidDetails['time_hi_and_version'] & 0x0fff) << 48;
            $timestamp |= $uuidDetails['time_mid'] << 32;
            $timestamp |= $uuidDetails['time_low'];
            $timestamp = $timestamp - 0x01B21DD213814000;
            $timestamp = $timestamp / 10000000;
            $timestamp = (int) $timestamp;
            try {
                $timestamp = new DateTime('@' . $timestamp);
                $timestamp = $timestamp->format(DateTimeInterface::ATOM);
            } catch (Throwable) {
                $timestamp = null;
            }
        } else {
            $timestamp = null;
        }
        $clock = $uuidDetails['clock_seq_hi_and_reserved'] << 8;
        $clock |= $uuidDetails['clock_seq_low'];
        $clock = $clock & 0x3fff;
        $node = str_pad(dechex($uuidDetails['node']), 12, '0', STR_PAD_LEFT);
        $node = str_split($node, 2);
        $node = implode(':', $node);
        $contents = str_split(str_replace('-', '', $uuid), 2);
        $contents = implode(':', $contents);
        return [
            'uuid' => $uuid,
            'single_integer' => self::integerId($uuid),
            'version' => $uuidDetails['version'],
            'variant' => $uuidDetails['variant'],
            'variant_name' => $uuidDetails['variant_name'],
            'contents_node' => $node,
            'contents_time' => $timestamp,
            'contents_clock' => $clock,
            'contents' => $contents,
            'time_low' => $uuidDetails['time_low'],
            'time_mid' => $uuidDetails['time_mid'],
            'time_hi_and_version' => $uuidDetails['time_hi_and_version'],
            'clock_seq_hi_and_reserved' => $uuidDetails['clock_seq_hi_and_reserved'],
            'clock_seq_low' => $uuidDetails['clock_seq_low'],
            'node' => $uuidDetails['node'],
        ];
    }

    /**
     * Calculate namespace and name.
     *
     * @param string $namespace namespace to calculate is uuid
     * @param string $name name to calculate
     * @param ?int $algorithm UUID::UUID_TYPE_MD5 or UUID::UUID_TYPE_SHA1 default is UUID::UUID_TYPE_SHA1
     * @return string calculated namespace and name
     */
    public static function calculateNamespaceAndName(
        string $namespace,
        string $name,
        ?int $algorithm = null
    ): string {
        $version = self::version($namespace);
        if ($version === null) {
            throw new InvalidArgumentException(
                'Invalid namespace'
            );
        }
        if (($algorithm !== self::UUID_TYPE_MD5 && $algorithm !== self::UUID_TYPE_SHA1)) {
            $algorithm = $version === 3 ? self::UUID_TYPE_MD5 : self::UUID_TYPE_SHA1;
        }
        // fallback to sha1 if algorithm is not valid
        $algorithm = $algorithm??self::UUID_TYPE_SHA1;
        // Get hexadecimal components of namespace
        $nHex = str_replace(['-', '{', '}'], '', $namespace);
        // Binary Value
        $nStr = '';
        // Convert Namespace UUID to bits
        for ($i = 0, $len = strlen($nHex); $i < $len; $i += 2) {
            $nStr .= chr(hexdec($nHex[$i] . $nHex[$i + 1]));
        }
        // Calculate hash value
        return $algorithm === self::UUID_TYPE_MD5 ? md5($nStr . $name) : sha1($nStr . $name);
    }

    /**
     * Generate a UUID.
     * @param int $version 1, 2, 3, 4, or 5
     * @param ?int $variant UUID variant to use UUID::UUID_VARIANT_* constants
     * @param ?int $type UUID type to use UUID::UUID_TYPE_* constants
     * @param string|null $hash
     * @param int|null $node the (maximum 48-bit) integer node (commonly mac address - hexdec($macHex))
     * @return string UUID v1, v2, v3, v4, or v5
     */
    public static function generate(
        int $version,
        ?int $variant = null,
        ?int $type = null,
        ?string $hash = null,
        ?int $node = null
    ): string {
        if ($variant === null) {
            $variant = match ($version) {
                1, 2 => self::UUID_VARIANT_DCE,
                default => self::UUID_VARIANT_RFC4122,
            };
        }
        if ($type === null) {
            $type = match ($version) {
                1, 2 => self::UUID_TYPE_TIME,
                3 => self::UUID_TYPE_MD5,
                5 => self::UUID_TYPE_SHA1,
                default => self::UUID_TYPE_RANDOM,
            };
        }

        if ($type !== self::UUID_TYPE_RANDOM
            && $type !== self::UUID_TYPE_TIME
            && $type !== self::UUID_TYPE_MD5
            && $type !== self::UUID_TYPE_SHA1
            && Consolidation::isHex($hash)
            && (strlen($hash) === 32 || strlen($hash) === 40)
        ) {
            $type = strlen($hash) === 32 ? self::UUID_TYPE_MD5 : self::UUID_TYPE_SHA1;
        }

        $variant = self::UUID_VARIANTS[$variant]??self::UUID_VARIANTS[self::UUID_VARIANT_NCS];
        if ($type === self::UUID_TYPE_MD5 || $type === self::UUID_TYPE_SHA1) {
            if (!$hash) {
                $hash = match ($type) {
                    self::UUID_TYPE_MD5 => md5(Random::bytes(16)), // random_bytes(16) is 128 bits
                    default => sha1(Random::bytes(16)),
                };
            } elseif ($type === self::UUID_TYPE_SHA1) {
                if (strlen($hash) < 40 && $version !== 5) {
                    throw new InvalidArgumentException(
                        'Invalid hash for UUID v5'
                    );
                }
                if (!Consolidation::isHex($hash)) {
                    if ($version === 5) {
                        throw new InvalidArgumentException(
                            'Invalid hash for UUID v5'
                        );
                    } elseif ($version === 3) {
                        throw new InvalidArgumentException(
                            'Invalid hash for UUID v3'
                        );
                    } else {
                        throw new InvalidArgumentException(
                            'Invalid hash for SHA1 UUID'
                        );
                    }
                }
            } else {
                if (strlen($hash) < 32 && $version !== 3) {
                    throw new InvalidArgumentException(
                        'Invalid hash for UUID v3'
                    );
                }
                if (!Consolidation::isHex($hash)) {
                    if ($version === 5) {
                        throw new InvalidArgumentException(
                            'Invalid hash for UUID v5'
                        );
                    } elseif ($version === 3) {
                        throw new InvalidArgumentException(
                            'Invalid hash for UUID v3'
                        );
                    } else {
                        throw new InvalidArgumentException(
                            'Invalid hash for MD5 UUID'
                        );
                    }
                }
            }

            // 32 bits for "time_low"
            $timeLow = hexdec(substr($hash, 0, 8)) & 0xffffffff;
            // 16 bits for "time_mid"
            $timeMid = hexdec(substr($hash, 8, 4)) & 0xffff;
            // 16 bits for "time_hi_and_version",
            $timeHi = hexdec(substr($hash, 12, 4)) & 0x0fff;
            // 16 bits, 8 bits for "clk_seq_hi_res",
            $clockSeqHi = hexdec(substr($hash, 16, 2)) & 0x3f;
            // 8 bits for "clk_seq_low",
            $clockSeqLow = hexdec(substr($hash, 18, 2)) & 0xff;
            // 48 bits for "node"
            $node = ($node??hexdec(substr($hash, 20, 12))) & 0xffffffffffff;
        } else {
            if ($type === self::UUID_TYPE_TIME) {
                // 60-bits timestamp
                // https://datatracker.ietf.org/doc/html/rfc4122#section-4.2.1
                // Get the current time as a 60-bit count of 100-nanosecond intervals
                // since 00:00:00.00, 15 October 1582
                $timestamp = (int) (microtime(true) * 10000000) + 0x01B21DD213814000;
                // timeLow 32 bits of time
                $timeLow = $timestamp & 0xffffffff;
                // timeMid 16 bits of timeMid
                $timeMid = ($timestamp >> 32) & 0xffff;
                // time high and version bits (12)
                $timeHi = ($timestamp >> 48) & 0x0fff;
            } else {
                $timeLow = Random::int(0, 0xffffffff); // random_int
                $timeMid = Random::int(0, 0xffff);
                $timeHi  = Random::int(0, 0x0fff);
            }
            // clock sequence high and reserved
            $clockSeqHi  = Random::int(0, 0xffff) & 0x3f;
            $clockSeqLow = Random::int(0, 0xff) & 0xff;
            $node = $node??Random::int(0, 0xffffffffffff);
            $node = $node & 0xffffffffffff;
        }
        $version = match ($version) {
            1 => 0x1000,
            2 => 0x2000,
            3 => 0x3000,
            5 => 0x5000,
            default => 0x4000,
        };
        $timeHi |= $version;
        // clock_seq_hi_and_variant
        $clockSeqHi |= $variant;
        return sprintf(
            '%08x-%04x-%04x-%02x%02x-%012x',
            $timeLow,
            $timeMid,
            $timeHi,
            $clockSeqHi,
            $clockSeqLow,
            $node
        );
    }

    /**
     * Generate a version 1 (random) UUID.
     *
     * @param int $variant
     * @return string
     * @link https://datatracker.ietf.org/doc/html/rfc4122#section-4.1.4
     */
    public static function v1(int $variant = self::UUID_VARIANT_DCE): string
    {
        return self::generate(1, $variant, self::UUID_TYPE_TIME);
    }

    /**
     * Generate a version 2 (DCE Security) UUID.
     *
     * @return string
     * @link https://tools.ietf.org/html/rfc4122#section-4.2
     */
    public static function v2() : string
    {
        // generate DCE UUID -> UUID V2
        return self::generate(2, self::UUID_VARIANT_DCE, self::UUID_TYPE_TIME);
    }

    /**
     * Generate a version 3 (MD5) UUID.
     *
     * @param string $namespace
     * @param string $name
     * @return string
     * @link https://tools.ietf.org/html/rfc4122#section-4.3
     */
    public static function v3(string $namespace, string $name): string
    {
        $hash = self::calculateNamespaceAndName($namespace, $name, self::UUID_TYPE_MD5);
        return self::generate(3, self::UUID_VARIANT_RFC4122, self::UUID_TYPE_MD5, $hash);
    }

    /**
     * Generate a version 4 (random) UUID.
     *
     * @return string UUID v4
     */
    public static function v4(): string
    {
        /**
         * Generate UUID v4
         */
        return self::generate(4, self::UUID_VARIANT_RFC4122, self::UUID_TYPE_RANDOM);
    }

    /**
     * Generate a version 5 (SHA-1) UUID.
     *
     * @param string $namespace see UUID::NAMESPACE_* constants
     * @param string $name name to calculate
     * @return string UUID v5
     */
    public static function v5(string $namespace, string $name): string
    {
        $hash = self::calculateNamespaceAndName($namespace, $name, self::UUID_TYPE_SHA1);
        return self::generate(5, self::UUID_VARIANT_RFC4122, self::UUID_TYPE_SHA1, $hash);
    }

    /**
     * @inheritdoc
     * Default string return uuid v4
     */
    public function __toString(): string
    {
        return self::v4();
    }
}
