<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Collection;

use SensitiveParameter;
use function is_array;

class Config extends Collection
{
    public function __construct(#[SensitiveParameter] iterable $param = [])
    {
        foreach ($param as $key => $value) {
            $param[$key] = !is_array($value)
                ? $value
                : new static($value);
        }
        parent::__construct($param);
    }

    public function merge(#[SensitiveParameter] iterable $param): void
    {
        foreach ($param as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function addDefaults(#[SensitiveParameter] iterable $param): void
    {
        foreach ($param as $key => $value) {
            if (!$this->has($key)) {
                $this->set($key, $value);
            }
        }
    }

    public function set($id, #[SensitiveParameter] $value): void
    {
        if (is_array($value)) {
            $value = new static($value);
        }
        parent::set($id, $value);
    }

    public function contain(#[SensitiveParameter] $param): bool
    {
        return parent::contain($param);
    }

    public function toArray(): array
    {
        $values = [];
        foreach ($this->all() as $key => $item) {
            $values[$key] = $item instanceof Config
                ? $item->toArray()
                : $item;
        }
        return $values;
    }

    public function __clone(): void
    {
        foreach ($this->data as $key => $v) {
            if ($v instanceof Config) {
                $this->data[$key] = clone $v;
            }
        }
    }
}
