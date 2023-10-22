<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts;

use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;
use function array_shift;
use function explode;
use function preg_replace;
use function str_starts_with;
use function strtolower;
use function substr_replace;
use function trim;

// todo @completion
abstract class AbstractIPResultData extends AbstractResultData
{
    public function __construct(WhoisResult $result)
    {
        parent::__construct(
            $result,
            static::REGISTRAR,
            $this->reparse($result)
        );
    }

    /**
     * @param string $name
     * @return string
     */
    protected function normalizeKeyName(string $name): string
    {
        $name = strtolower(trim($name));
        return preg_replace('~[^a-z0-9]+|[_\-\s]+~', '_', $name);
    }

    protected function getAliasDataName(string $key): string
    {
        $key = $this->normalizeKeyName($key);
        $key =  match ($key) {
            'irt'   => 'incident_response_team',
            'inetnum',
            'netrange' => 'network_range',
            'aut_num' => 'autonomous_system_number',
            'tech_c' => 'technical_contact',
            'admin_c' => 'administrative_contact',
            'owner_c' => 'owner_contact',
            'ownerid' => 'owner_id',
            'abuse_c' => 'abuse_contact',
            'netname' => 'network_name',
            'descr'   => 'description',
            'mnt_ref'   => 'maintainer_reference',
            'mnt_by'   => 'maintainer',
            'mnt_lower'   => 'sub_maintainer',
            'mnt_irt'   => 'maintainer_incident_response_team',
            'mnt_routes'   => 'maintainer_routes',
            'nic_hdl'   => 'nic_handle',
            'org_name'   => 'organization_name',
            'org_type'   => 'organization_type',
            'nserver' => 'name_server',
            'nsstat' => 'name_server_status',
            'nslastaa' => 'name_server_last_a_address',
            'changed' => 'updated_at',
            'created' => 'created_at',
            // cidr
            'inetrev' => 'cidr',
            default => $key
        };
        if (str_starts_with($key, 'tech_')) {
            $key = substr_replace(
                $key,
                'technical_',
                0,
                5
            );
        }
        if (str_starts_with($key, '_c')) {
            $key = substr_replace(
                $key,
                '_contact',
                0,
                -2
            );
        }

        return $key;
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
