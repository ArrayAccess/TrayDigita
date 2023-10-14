<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use ArrayAccess\TrayDigita\Util\Filter\Ip as IpValidator;
use ArrayAccess\TrayDigita\Util\Whois\Abstracts\AddressStorage;

class Ip extends AddressStorage
{
    protected int|false|null $ipVersion = null;

    protected ?bool $isLocal = null;

    public function getVersion(): int|null
    {
        $this->ipVersion ??= IpValidator::version($this->getAddress())?:false;
        return $this->ipVersion?:null;
    }
    public function isLocal() : bool
    {
        $this->isLocal ??= IpValidator::isLocalIP($this->getAddress());
        return $this->isLocal;
    }

    public function isValid(): bool
    {
        $version = $this->getVersion();
        return $version === IpValidator::IP4 || $version === IpValidator::IP6;
    }

    public function __serialize(): array
    {
        return [
            'ip' => $this->getAddress(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->address = $data['ip'];
    }
}
