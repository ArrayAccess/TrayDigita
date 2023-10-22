<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\WhoisRegistrar;

use ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts\DomainResultAbstract;
use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;
use DateTimeZone;
use function array_key_exists;
use function array_pop;
use function array_values;
use function explode;
use function is_array;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function substr_replace;

class Jprs extends DomainResultAbstract
{
    const REGISTRAR = 'Japan Registry Services Co., Ltd';

    public function __construct(WhoisResult $result)
    {
        $this->timeZone = new DateTimeZone('JST');
        parent::__construct($result);
    }

    protected function getAliasDataName(string $key, ?string $section = null): string
    {
        $key = parent::getAliasDataName($key);
        return match ($section) {
            'domain' => match ($key) {
                'domain_domain_name',
                'domain_name' => 'domain_name',
                default => $key
            },
            default => $key,
        };
    }

    protected function parseData(WhoisResult $result): array
    {
        $array = self::DEFAULT_METADATA;
        $current_section = null;
        $previousKey = null;
        foreach (explode("\n", $result->getData()) as $result) {
            $trimmedResult = trim($result);
            if ($trimmedResult === '') {
                $previousKey = null;
                continue;
            }
            if (str_ends_with($trimmedResult, 'Information:')) {
                preg_match('~^(.+\S)\s*Information:~', $trimmedResult, $match);
                if (!empty($match)) {
                    $current_section = $this->normalizeKeyName(trim($match[1]));
                }
                continue;
            }
            if (!$current_section) {
                continue;
            }
            $key = null;
            $item = null;
            if (str_starts_with($trimmedResult, '[') && str_contains($trimmedResult, '[')) {
                preg_match('~^\[\s*([^]]+)\s*]\s*(.*)$~', $trimmedResult, $match);
                if (!empty($match)) {
                    $key = $match[1];
                    $previousKey = $key;
                    $item = $match[2];
                }
            }

            if (!$key) {
                if (!$previousKey) {
                    continue;
                }
                $key = $previousKey;
                $item = $trimmedResult;
            }
            $key = $this->getAliasDataName("{$current_section}_$key", $current_section);
            $item = $item ? $this->filterValue($key, $item) : $item;
            if (isset($array[$key])) {
                if (is_array($array[$key])) {
                    if ($item) {
                        $array[$key][] = $item;
                    }
                } else {
                    $array[$key] .= ', '. $item;
                }
            } else {
                $array[$key] = $item;
            }
        }

        $address = $array['admin_postal_address']??[];
        unset($array['admin_postal_address']);
        $array['admin_city'] ??= array_pop($address)?:null;
        $array['admin_street'] = array_values($address);
        foreach ($array as $k => $i) {
            if (!str_starts_with($k, 'admin_')) {
                continue;
            }
            $key = substr_replace($k, 'registrant_', 0, 6);
            if (array_key_exists($key, $array) && empty($array[$key])) {
                $array[$key] = $i;
            }
        }
        return $array;
    }
}
