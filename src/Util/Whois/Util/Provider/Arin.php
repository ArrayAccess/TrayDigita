<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\Provider;

use ArrayAccess\TrayDigita\Util\Whois\DomainIpServer;
use ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts\IpDataResultAbstract;

// todo @completion
class Arin extends IpDataResultAbstract
{
    const PROVIDER = DomainIpServer::ARIN;
}
