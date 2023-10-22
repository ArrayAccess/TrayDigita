<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use ArrayAccess\TrayDigita\Util\Network\Dns;
use JsonSerializable;
use Serializable;
use Stringable;
use Throwable;
use function is_bool;
use function is_string;
use function preg_match;
use function serialize;
use function sha1;
use function strtolower;
use function trim;
use function unserialize;

final class WhoisResult implements Serializable, Stringable, JsonSerializable
{
    const TYPE_IP = 'ip';
    const TYPE_DOMAIN = 'domain';

    final const CACHE_IP_PREFIX = 'whois_result_ip_domain_';

    private string $data;

    private string $hash;

    private string $server;

    private WhoisResult|false|null $alternativeResult = null;

    private ?string $currentHash = null;

    private ?string $alternativeWhoisServer = null;

    private ?string $extraCommand;

    public function __construct(
        protected Domain|Ip $address,
        string $data,
        string $server,
        ?string $extraCommand
    ) {
        $this->extraCommand = $extraCommand;
        $this->data = $data;
        $this->server = strtolower(trim($server));
        $this->hash   = sha1(serialize([
            $this->server,
            $extraCommand,
            $data,
            $address
        ]));
    }

    public function getExtraCommand(): ?string
    {
        return $this->extraCommand;
    }

    public function getServer(): string
    {
        return $this->server;
    }

    public function getAlternativeWhoisServer() : ?string
    {
        if ($this->alternativeWhoisServer !== null) {
            return $this->alternativeWhoisServer?:null;
        }
        $this->alternativeWhoisServer = '';
        if ($this->address instanceof Ip) {
            return null;
        }
        preg_match(
            '~^\s*(?:Registrar\s*)?Whois(?:[\s-]*Server)?\s*:\s*(\S+)\s*$~mi',
            $this->getData(),
            $match
        );
        $this->alternativeWhoisServer = strtolower(trim($match[1]??''));
        return $this->alternativeWhoisServer?:null;
    }


    public function getAlternativeData(
        Checker $checker,
        bool $useCache = true
    ): ?WhoisResult {
        if ($this->alternativeResult !== null) {
            return $this->alternativeResult;
        }
        $this->alternativeResult = false;
        $alternateServer = $this->getAlternativeWhoisServer();
        if (!$alternateServer || $alternateServer === $this->server) {
            return $this->alternativeResult?:null;
        }
        try {
            $cacheKey = self::CACHE_IP_PREFIX . sha1($alternateServer);
            $cache = $checker->getCache();
            $cacheItem = $cache?->getItem($cacheKey);
            $cachedIp = $cacheItem?->get();
            if ($useCache && $cacheItem && (is_string($cachedIp) || ($bool = is_bool($cachedIp)))) {
                if (!empty($bool)) {
                    return null;
                }
                $ip = $cachedIp;
            } else {
                $record = Dns::record('A', $alternateServer, timeout: 2)??[];
                $record = reset($record) ?? [];
                $ip = $record['ip'] ?? null;
                if ($cacheItem) {
                    $cacheItem->set($ip??false);
                    $cacheItem->expiresAfter(3600);
                    $cache->save($cacheItem);
                }
            }
            if (!$ip || (new Ip($ip))->isLocal()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }
        return $this->alternativeResult = $checker
            ->whois(
                $this->address,
                $useCache,
                $alternateServer
            );
    }

    public function getType(): string
    {
        return $this->address instanceof Domain
            ? self::TYPE_DOMAIN
            : self::TYPE_IP;
    }
    public function isIp() : bool
    {
        return $this->getType() === self::TYPE_IP;
    }

    public function isDomain() : bool
    {
        return $this->getType() === self::TYPE_DOMAIN;
    }

    public function isLocalIP(): bool
    {
        return $this->isIp() && $this->getAddress()->isLocal();
    }

    public function isLocalDomain(): bool
    {
        return $this->isDomain() && $this->getAddress()->isLocal();
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getAddress(): Domain|Ip
    {
        return $this->address;
    }

    public function isValid() : bool
    {
        if (!$this->currentHash) {
            $this->currentHash = sha1(serialize([
                $this->server,
                $this->extraCommand,
                $this->data,
                $this->address
            ]));
        }
        return $this->hash === $this->currentHash;
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
            'domain' => $this->getAddress(),
            'hash' => $this->getHash(),
            'data' => $this->getData(),
            'server' => $this->getServer(),
            'extra_command' => $this->getExtraCommand(),
            'alternative_result' => $this->alternativeResult
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
        $this->address = $data['domain'];
        $this->hash = $data['hash'];
        $this->server = $data['server'];
        $this->extraCommand = $data['extra_command']??null;
        $this->alternativeResult = $data['alternative_result'];
        $this->alternativeWhoisServer = null;
    }

    public function __toString(): string
    {
        return $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->__serialize();
    }
}
