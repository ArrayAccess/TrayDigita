<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util;

use ArrayAccess\TrayDigita\Util\Whois\DomainIpServer;
use ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts\AbstractResultData;
use ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar\Afrinic;
use ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar\Apnic;
use ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar\Arin;
use ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar\Common as CommonIP;
use ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar\Lacnic;
use ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar\LocalData as LocalIpData;
use ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar\Ripe;
use ArrayAccess\TrayDigita\Util\Whois\Util\WhoisRegistrar\Common as CommonDomain;
use ArrayAccess\TrayDigita\Util\Whois\Util\WhoisRegistrar\Jprs;
use ArrayAccess\TrayDigita\Util\Whois\Util\WhoisRegistrar\LocalData as LocalDomainData;
use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;
use function strtolower;

class WhoisDataConversionFactory
{
    public function parse(WhoisResult $result) : AbstractResultData
    {
        return !$result->isIp()
            ? $this->parseDomain($result)
            : $this->parseIP($result);
    }

    protected function parseIP(WhoisResult $result) : AbstractResultData
    {
        if ($result->isLocalIP()) {
            return new LocalIpData($result);
        }
        $server = strtolower($result->getServer());
        $type = DomainIpServer::IP_WHOIS_SERVER_PROVIDER[$server]??null;

        return match ($type) {
            DomainIpServer::APNIC => new Apnic($result),
            DomainIpServer::ARIN => new Arin($result),
            DomainIpServer::LACNIC => new Lacnic($result),
            DomainIpServer::AFRINIC => new Afrinic($result),
            DomainIpServer::RIPE => new Ripe($result),
            default => new CommonIP($result),
        };
    }

    protected function parseDomain(WhoisResult $result) : AbstractResultData
    {
        if ($result->isLocalDomain()) {
            return new LocalDomainData($result);
        }
        $server = strtolower($result->getServer());
        return match ($server) {
            'whois.jprs.jp' => new Jprs($result),
            default => new CommonDomain($result)
        };
    }
}
