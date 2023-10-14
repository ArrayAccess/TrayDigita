<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n\Records;

use JsonSerializable;
use Serializable;
use function serialize;
use function unserialize;

final class CountryCode implements Serializable, JsonSerializable
{
    public function __construct(protected string $alpha2, protected string $alpha3)
    {
    }

    public function getAlpha2(): string
    {
        return $this->alpha2;
    }

    public function getAlpha3(): string
    {
        return $this->alpha3;
    }

    public function serialize() : string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __serialize(): array
    {
        return [
            'alpha2' => $this->alpha2,
            'alpha3' => $this->alpha3,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->alpha2 = $data['alpha2'];
        $this->alpha3 = $data['alpha3'];
    }

    public function jsonSerialize(): array
    {
        return $this->__serialize();
    }
}
