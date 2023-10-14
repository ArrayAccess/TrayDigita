<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Google;

use function chr;
use function preg_replace;
use function str_repeat;
use function strlen;
use function strtoupper;
use function unpack;

class Converter
{
    /**
     * Encode 32
     *
     * @param scalar|mixed $string
     *
     * @return string|false
     */
    public static function base32Encode(mixed $string): string|false
    {
        $string = (string) $string;
        $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567=';
        // Empty string results in empty string
        if ('' === $string) {
            return '';
        }

        $encoded = '';

        //Set the initial values
        $n = $bitLen = $val = 0;
        $len = strlen($string);
        //Pad the end of the string - this ensures that there are enough zeros
        $string .= str_repeat(chr(0), 4);
        // Explode string into integers
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $chars = unpack('C*', $string, 0);
        if ($chars === false) {
            return false;
        }
        while ($n < $len || 0 !== $bitLen) {
            //If the bit length has fallen below 5, shift left 8 and add the next character.
            if ($bitLen < 5) {
                $val = $val << 8;
                $bitLen += 8;
                $n++;
                $val += $chars[$n];
            }
            $shift = $bitLen - 5;
            $encoded .= ($n - (int)($bitLen > 8) > $len && 0 === $val) ? '=' : $alpha[$val >> $shift];
            $val = $val & ((1 << $shift) - 1);
            $bitLen -= 5;
        }
        return $encoded;
    }

    /**
     * Decode 32
     *
     * @param string $base32String
     *
     * @return string
     */
    public static function base32Decode(string $base32String): string
    {
        $base32String = strtoupper($base32String);
        $base32String = preg_replace('~[^A-Z2-7]~', '', $base32String);
        $decoded = '';
        if (!$base32String) {
            return $decoded;
        }
        $mapping = [
            "=" => 0,
            "A" => 0,
            "B" => 1,
            "C" => 2,
            "D" => 3,
            "E" => 4,
            "F" => 5,
            "G" => 6,
            "H" => 7,
            "I" => 8,
            "J" => 9,
            "K" => 10,
            "L" => 11,
            "M" => 12,
            "N" => 13,
            "O" => 14,
            "P" => 15,
            "Q" => 16,
            "R" => 17,
            "S" => 18,
            "T" => 19,
            "U" => 20,
            "V" => 21,
            "W" => 22,
            "X" => 23,
            "Y" => 24,
            "Z" => 25,
            "2" => 26,
            "3" => 27,
            "4" => 28,
            "5" => 29,
            "6" => 30,
            "7" => 31
        ];
        //Set the initial values
        $len = strlen($base32String);
        $n = 0;
        $bitLen = 5;
        $val = $mapping[$base32String[0]];
        while ($n < $len) {
            if ($bitLen < 8) {
                $val = $val << 5;
                $bitLen += 5;
                $n++;
                $ext = $base32String[$n] ?? '=';
                if ('=' === $ext) {
                    $n = $len;
                }
                $val += $mapping[$ext];
            } else {
                $shift = $bitLen - 8;
                $decoded .= chr($val >> $shift);
                $val = $val & ((1 << $shift) - 1);
                $bitLen -= 8;
            }
        }

        return $decoded;
    }
}
