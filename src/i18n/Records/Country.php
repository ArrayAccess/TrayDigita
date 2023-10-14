<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\i18n\Records;

use ArrayAccess;
use ArrayIterator;
use ArrayAccess\TrayDigita\i18n\Countries;
use IteratorAggregate;
use JsonSerializable;
use Serializable;
use Stringable;
use Traversable;
use function serialize;
use function strtoupper;
use function unserialize;

final class Country implements
    ArrayAccess,
    IteratorAggregate,
    Serializable,
    Stringable,
    JsonSerializable
{
    /**
     * @var array<string, Continent>
     */
    private static array $continents = [];

    /**
     * @var array<string, CountryCode>
     */
    private static array $countryCodes = [];

    /**
     * @var array<string, CurrencyList>
     */
    private static array $currencyLists = [];

    /**
     * @var array<string, TimeZones>
     */
    private static array $timeZoneLists = [];

    public function __construct(protected string $code)
    {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isValid() : bool
    {
        return isset(Countries::LISTS[$this->code]);
    }

    /**
     * @return ?array{
     *     name: string,
     *     continent: array {
     *         code: string,
     *         name: string
     *     },
     *     code: array {
     *         alpha2: string,
     *         alpha3: string,
     *     },
     *     numeric: numeric-string,
     *     currencies: array<string>,
     *     timezones: array<string>,
     *     dial_code: string
     *  }
     */
    public function asArray(): ?array
    {
        return Countries::LISTS[$this->code] ?? null;
    }

    public function getName(): ?string
    {
        return ($this->asArray() ?? [])['name'] ?? null;
    }

    public function getNumeric(): ?string
    {
        return ($this->asArray() ?? [])['numeric'] ?? null;
    }

    public function getContinent(): ?Continent
    {
        if (($details = $this->asArray()) === null) {
            return null;
        }
        return self::$continents[$details['continent']['code']] ??= new Continent(
            $details['continent']['code'],
            $details['continent']['name']
        );
    }

    public function getCurrencies(): ?CurrencyList
    {
        if (($details = $this->asArray()) === null) {
            return null;
        }

        return self::$currencyLists[$this->code] ??= new CurrencyList(...$details['currencies']);
    }

    public function getCountryCode(): ?CountryCode
    {
        if (($details = $this->asArray()) === null) {
            return null;
        }
        return self::$countryCodes[$this->code] ??= new CountryCode(
            $details['code']['alpha2'],
            $details['code']['alpha3']
        );
    }

    public function getDialCode(): ?string
    {
        return ($this->asArray() ?? [])['dial_code'] ?? null;
    }

    public function getTimeZones(): ?TimeZones
    {
        if (($details = $this->asArray()) === null) {
            return null;
        }
        return self::$timeZoneLists[$this->code] ??= new TimeZones(...$details['timezones']);
    }

    public function getDetails() : ?array
    {
        if (!isset(Countries::LISTS[$this->code])) {
            return null;
        }

        return [
            'name' => $this->getName(),
            'continent' => $this->getContinent(),
            'code' => $this->getCountryCode(),
            'numeric' => $this->getNumeric(),
            'currencies' => $this->getCurrencies(),
            'timezones' => $this->getTimeZones(),
            'dial_code' => $this->getDialCode(),
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->isValid() && match ($offset) {
                'name',
                'continent',
                'code',
                'numeric',
                'currencies',
                'timezones',
                'dial_code' => true,
                default => false
        };
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'name' => $this->getName(),
            'continent' => $this->getContinent(),
            'code' => $this->getCountryCode(),
            'numeric' => $this->getNumeric(),
            'currencies' => $this->getCurrencies(),
            'timezones' => $this->getTimeZones(),
            'dial_code' => $this->getDialCode(),
            default => null
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getDetails());
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
            'code' => $this->getCode(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->code = strtoupper($data['code']);
    }

    public function __toString(): string
    {
        return $this->getCode();
    }

    public function jsonSerialize(): array
    {
        return $this->getDetails();
    }
}
