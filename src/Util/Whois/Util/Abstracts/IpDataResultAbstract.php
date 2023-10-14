<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts;

use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;
use function array_shift;
use function explode;
use function preg_replace;
use function strtolower;
use function trim;

// todo @completion
abstract class IpDataResultAbstract extends AbstractDataResult
{
    const PROVIDER = 'UNKNOWN';

    public function __construct(WhoisResult $result)
    {
        parent::__construct(
            true,
            $result->getAddress()->getAddress(),
            static::PROVIDER,
            $this->reparse($result)
        );
    }
    protected function getAliasDataName(string $key): string
    {
        $key = trim($key);
        return match (strtolower($key)) {
            'irt'   => 'incident-response-team',
            'inetnum',
            'netrange' => 'network-range',
            'aut-num' => 'autonomous-system-number',
            'tech-c' => 'technical-contact',
            'admin-c' => 'administrative-contact',
            'owner-c' => 'owner-contact',
            'ownerid' => 'owner-id',
            'abuse-c' => 'abuse-contact',
            'netname' => 'network-name',
            'descr'   => 'description',
            'mnt-ref'   => 'maintainer-reference',
            'mnt-by'   => 'maintainer',
            'mnt-lower'   => 'sub-maintainer',
            'mnt-irt'   => 'maintainer-incident-response-team',
            'mnt-routes'   => 'maintainer-routes',
            'nic-hdl'   => 'nic-handle',
            'org-name'   => 'organization-name',
            'org-type'   => 'organization-type',
            'nserver' => 'name-server',
            'nsstat' => 'name-server-status',
            'nslastaa' => 'name-server-last-a-address',
            'changed' => 'updated_at',
            'created' => 'created_at',
            // cidr
            'inetrev' => 'cidr',
            default => $key
        };
    }

    protected function reparse(WhoisResult $result) : array
    {
        $data = trim($result->getData());
        $result = [];
        $start = true;
        $increment = 0;
        foreach (explode("\n", $data) as $item) {
            $item = trim($item);
            if (str_starts_with($item, '%')) {
                continue;
            }
            if (!$start && $item === '') {
                $start = true;
                $increment++;
                continue;
            }
            $item = explode(':', $item, 2);
            if (count($item) !== 2) {
                continue;
            }
            $key = array_shift($item);
            $key = $this->getAliasDataName($key);
            $item = trim(array_shift($item) ??'');
            $item = trim(preg_replace('~(?:[\s]+|#.+$)~', ' ', $item));
            if ($key === 'phone') {
                $item = preg_replace('~^tel\s*:\s*~', '', $item);
            }
            $result[$increment][$key][] = $item;
            $start = false;
        }

        return $result;
    }
}
