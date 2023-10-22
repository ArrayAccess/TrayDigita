<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts;

use ArrayAccess\TrayDigita\Util\Whois\DomainIpServer;
use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;
use function preg_match;
use function strtolower;

abstract class AbstractResultData
{
    const TYPE_IP = 'IP';

    const TYPE_DOMAIN = 'DOMAIN';

    const REGISTRAR = 'UNKNOWN';

    protected string $type;

    protected string $address;

    protected ?string $provider;

    protected array $data = [];

    protected string $server;

    public function __construct(
        WhoisResult $result,
        ?string $registrar,
        array $data
    ) {
        $this->address = $result->getAddress()->getAsciiName();
        $this->type = $result->isIp() ? self::TYPE_IP : self::TYPE_DOMAIN;
        $this->data = $data;
        $this->server = $result->getServer();
        $this->provider = $registrar;
        if (!$registrar || $registrar === self::REGISTRAR) {
            if ($result->isIp()) {
                $providers = DomainIpServer::IP_WHOIS_SERVER_PROVIDER;
                $this->provider = $providers[strtolower($this->server)]??$this->provider;
            } else {
                preg_match(
                    '~^Registrar\s*:\s*(.*)$~m',
                    $result->getData(),
                    $match
                );
                if (!empty($match[1])) {
                    $this->provider = trim($match[1]);
                }
            }
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }
}
