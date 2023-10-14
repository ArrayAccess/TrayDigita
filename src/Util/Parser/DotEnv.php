<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Parser;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use function array_shift;
use function explode;
use function file;
use function implode;
use function is_file;
use function is_numeric;
use function is_readable;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;

class DotEnv
{
    private static function doCasting(string $data) : float|bool|int|string|null
    {
        return match (strtolower($data)) {
            'null' => null,
            'true' => true,
            'false' => false,
            default => is_numeric($data)
                ? (
                str_contains($data, '.')
                    ? (float) $data
                    : (int) $data
                ) : $data
        };
    }

    public static function fromFile(
        string $file,
        bool $casting = false,
        bool $safe = false
    ) : array {
        if (!is_file($file) || !is_readable($file)) {
            if (!$safe) {
                throw new InvalidArgumentException(
                    sprintf('File %s is not exist.', $file)
                );
            }
            return [];
        }

        return self::fromArray(file($file)?:[], $casting);
    }

    private static function fromArray(array $lines, bool $casting = false) : array
    {
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $dataArray = explode('=', $line, 2);
            $count = count($dataArray);
            if ($count === 0) {
                continue;
            }
            $key = array_shift($dataArray);
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $value = $count < 2 ? null : implode('=', $dataArray);
            if (!$value) {
                $result[$key] = null;
                continue;
            }

            $length = strlen($value);
            $inQuote = $length > 1
                && ($value[0] === '"' || $value[0] === "'")
                && $value[0] === $value[$length-1];
            if (!$inQuote && $casting) {
                $value = self::doCasting($value);
            } elseif ($inQuote) {
                $first = $value[0];
                $quoted = preg_quote($first, '~');
                $value = substr($value, 1, -1);
                // skip invalid
                if (preg_match("~([^\\\]|^)$quoted~", $value)) {
                    continue;
                }
                $value = str_replace("\\$first", $first, $value);
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * @param string $content
     * @param bool $casting
     *
     * @return array
     */
    public static function fromString(
        string $content,
        bool $casting = false
    ) : array {
        $lines = explode("\n", $content);
        return self::fromArray($lines, $casting);
    }
}
