<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n\Records;

use ArrayAccess\TrayDigita\i18n\Currencies;
use JsonSerializable;
use Serializable;
use function serialize;
use function unserialize;

final class Currency implements Serializable, JsonSerializable
{
    public function __construct(protected string $code)
    {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isValid() : bool
    {
        return isset(Currencies::LIST[$this->code]);
    }

    public function getName(): ?string
    {
        return Currencies::LIST[$this->code]['name']??null;
    }

    public function getSymbol(): ?string
    {
        return Currencies::LIST[$this->code]['symbol']??null;
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
        return ['code' => $this->code];
    }

    public function __unserialize(array $data): void
    {
        $this->code = $data['code'];
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'symbol' => $this->getSymbol()
        ];
    }
}
