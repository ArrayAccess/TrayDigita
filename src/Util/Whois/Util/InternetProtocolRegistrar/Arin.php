<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\InternetProtocolRegistrar;

use ArrayAccess\TrayDigita\Util\Whois\DomainIpServer;
use ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts\AbstractIPResultData;

// todo @completion
class Arin extends AbstractIPResultData
{
    const REGISTRAR = DomainIpServer::ARIN;
}
