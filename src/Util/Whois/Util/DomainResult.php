<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util;

use ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts\AbstractDataResult;
use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;

// @todo completion
class DomainResult extends AbstractDataResult
{
    public function __construct(WhoisResult $result)
    {
        parent::__construct(
            false,
            $result->getAddress()->getAsciiName()?:$result->getAddress()->getAddress(),
            null,
            $this->parseData($result)
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function parseData(WhoisResult $result) : array
    {
        return [];
    }
}
