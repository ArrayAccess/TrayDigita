<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use Serializable;
use function preg_match;
use function serialize;
use function sha1;
use function trim;
use function unserialize;

final class WhoisResult implements Serializable
{
    const TYPE_IP = 'ip';
    const TYPE_DOMAIN = 'domain';

    protected string $data;

    protected string $hash;

    protected string $server;

    private ?string $currentHash = null;

    private ?string $alternativeWhoisServer = null;

    public function __construct(
        protected Domain|Ip $address,
        string $data,
        string $server
    ) {
        $this->data = $data;
        $this->server = $server;
        $this->hash   = sha1(serialize([$server, $data, $address]));
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
        $this->alternativeWhoisServer = trim($match[1]??'');
        return $this->alternativeWhoisServer;
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
            $this->currentHash = sha1(serialize([$this->server, $this->data, $this->address]));
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
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
        $this->address = $data['domain'];
        $this->hash = $data['hash'];
        $this->server = $data['server'];
        $this->alternativeWhoisServer = null;
    }
}
