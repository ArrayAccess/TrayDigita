<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util;

use ArrayAccess\TrayDigita\Util\Whois\DomainIpServer;
use ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts\AbstractDataResult;
use ArrayAccess\TrayDigita\Util\Whois\Util\Provider\Afrinic;
use ArrayAccess\TrayDigita\Util\Whois\Util\Provider\Apnic;
use ArrayAccess\TrayDigita\Util\Whois\Util\Provider\Arin;
use ArrayAccess\TrayDigita\Util\Whois\Util\Provider\Common;
use ArrayAccess\TrayDigita\Util\Whois\Util\Provider\Lacnic;
use ArrayAccess\TrayDigita\Util\Whois\Util\Provider\LocalDataResult;
use ArrayAccess\TrayDigita\Util\Whois\Util\Provider\Ripe;
use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;
use function strtolower;

class DataConversion
{
    public function parse(WhoisResult $result) : AbstractDataResult
    {
        if (!$result->isIp()) {
            return $this->parseDomain($result);
        }

        if ($result->getAddress()->isLocal()) {
            return new LocalDataResult($result);
        }

        return $this->parseIP($result);
    }

    protected function parseIP(WhoisResult $result) : AbstractDataResult
    {
        $server = strtolower($result->getServer());
        $type = DomainIpServer::IP_WHOIS_SERVER_PROVIDER[$server]??null;

        return match ($type) {
            DomainIpServer::APNIC => new Apnic($result),
            DomainIpServer::ARIN => new Arin($result),
            DomainIpServer::LACNIC => new Lacnic($result),
            DomainIpServer::AFRINIC => new Afrinic($result),
            DomainIpServer::RIPE => new Ripe($result),
            default => new Common($result),
        };
    }

    protected function parseDomain(WhoisResult $result) : AbstractDataResult
    {
        return new DomainResult($result);
    }
}
