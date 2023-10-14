<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n\Records;

use ArrayIterator;
use ArrayAccess\TrayDigita\i18n\Currencies;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Serializable;
use Traversable;
use function serialize;
use function unserialize;

final class CurrencyList implements
    IteratorAggregate,
    Serializable,
    Countable,
    JsonSerializable
{
    private static array $currencyRecords = [];

    /**
     * @var array<string, Currency>
     */
    protected array $currencyCodes = [];

    public function __construct(string ...$currencyCodes)
    {
        foreach ($currencyCodes as $code) {
            if (isset(Currencies::LIST[$code])) {
                self::$currencyRecords[$code] ??= new Currency($code);
            }
            $this->currencyCodes[$code] = self::$currencyRecords[$code]??new Currency($code);
        }
    }

    /**
     * @return array<string, Currency>
     */
    public function getCurrencyCodes(): array
    {
        return $this->currencyCodes;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->currencyCodes);
    }

    public function serialize() : string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __serialize() : array
    {
        return [
            'code' => $this->getCurrencyCodes()
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->currencyCodes = $data['code'];
    }

    public function count(): int
    {
        return count($this->getCurrencyCodes());
    }

    public function jsonSerialize(): array
    {
        return $this->getCurrencyCodes();
    }
}
