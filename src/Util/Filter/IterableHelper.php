<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use Generator;

class IterableHelper
{
    public static function yield(iterable $data): Generator
    {
        foreach ($data as $key => $item) {
            yield $key => $item;
        }
    }

    public static function keys(iterable $data): array
    {
        return \array_keys(self::all($data));
    }

    public static function all(iterable $data): array
    {
        return \is_array($data) ? $data : \iterator_to_array($data);
    }

    public static function has(iterable $data, string|float|int $key) : bool
    {
        return self::exists($data, static fn ($k) => $k === $key);
    }

    public static function contains(iterable $data, $expected) : bool
    {
        return self::exists($data, static fn ($k, $d) => $expected === $d);
    }

    public static function values(iterable $data) : array
    {
        return \array_values(self::all($data));
    }

    public static function count(iterable $data) : int
    {
        return \is_array($data) ? \count($data) : \iterator_count($data);
    }

    public static function exists(iterable $data, callable $mapping) : bool
    {
        foreach ($data as $key => $datum) {
            if ($mapping($key, $datum)) {
                return true;
            }
        }

        return false;
    }

    public static function until(iterable $data, callable $mapping): mixed
    {
        foreach ($data as $key => $datum) {
            if ($mapping($key, $datum)) {
                return $datum;
            }
        }
        return null;
    }

    public static function every(iterable $data, callable $mapping): void
    {
        foreach ($data as $key => $datum) {
            $data[$key] = $mapping($key, $datum);
        }
    }

    public static function map(iterable $data, callable $mapping): array
    {
        $result = [];
        foreach ($data as $key => $datum) {
            unset($data[$key]); // save resource
            $result[$key] = $mapping($key, $datum);
        }
        return $result;
    }

    public static function each(iterable $data, callable $mapping) : array
    {
        $result = [];
        foreach ($data as $k => $datum) {
            $key = $k;
            $datum = $mapping($key, $datum);
            $result[$key] = $datum;
        }
        return $result;
    }
}
