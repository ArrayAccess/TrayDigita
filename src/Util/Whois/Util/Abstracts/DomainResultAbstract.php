<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts;

use ArrayAccess\TrayDigita\Util\Whois\WhoisResult;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;
use function array_shift;
use function explode;
use function idn_to_ascii;
use function idn_to_utf8;
use function is_array;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strip_tags;
use function strtolower;
use function trim;
use const DATE_RFC7231;

// @todo completion
abstract class DomainResultAbstract extends AbstractResultData
{
    final const DEFAULT_METADATA = [
        'whois_server' => null,
        'domain_id' => null,
        'domain_name' => null,
        'domain_whois_server' => null,
        'domain_creation_date' => null,
        'domain_updated_date' => null,
        'domain_expiration_date' => null,
        'domain_whois_last_update' => null,
        'domain_dnssec' => null,
        'domain_status' => [],
        'domain_name_server' => [],

        'registrar_id' => null,
        'registrar_name' => null,
        'registrar_url' => null,
        'registrar_abuse_contact_email' => null,
        'registrar_abuse_contact_phone' => null,

        'registrant_id' => null,
        'registrant_name' => null,
        'registrant_url' => null,
        'registrant_organization' => null,
        'registrant_street' => [],
        'registrant_city' => null,
        'registrant_state_province' => null,
        'registrant_postal_code' => null,
        'registrant_country' => null,
        'registrant_phone' => [],
        'registrant_fax' => [],
        'registrant_email' => null,

        // admin
        'admin_id' => null,
        'admin_name' => null,
        'admin_url' => null,
        'admin_organization' => null,
        'admin_street' => [],
        'admin_city' => null,
        'admin_state_province' => null,
        'admin_postal_code' => null,
        'admin_country' => null,
        'admin_phone' => [],
        'admin_fax' => [],
        'admin_email' => null,

        // technical
        'technical_id' => null,
        'technical_name' => null,
        'technical_url' => null,
        'technical_organization' => null,
        'technical_street' => [],
        'technical_city' => null,
        'technical_state_province' => null,
        'technical_postal_code' => null,
        'technical_country' => null,
        'technical_phone' => [],
        'technical_fax' => [],
        'technical_email' => null,

        // technical
        'billing_id' => null,
        'billing_name' => null,
        'billing_url' => null,
        'billing_organization' => null,
        'billing_street' => [],
        'billing_city' => null,
        'billing_state_province' => null,
        'billing_postal_code' => null,
        'billing_country' => null,
        'billing_phone' => [],
        'billing_fax' => [],
        'billing_email' => null,

        'url_icann_data_report' => null,
    ];

    protected ?DateTimeZone $timeZone = null;

    public function __construct(WhoisResult $result)
    {
        parent::__construct(
            $result,
            null,
            $this->parseData($result)
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
        $key = preg_replace_callback(
            '~(^|_)(tech|admn?|contact|org\.?)(_|$)~',
            static function ($e) {
                return $e[1]
                    . match ($e[2]) {
                        'tech' => 'technical',
                        'adm',
                        'contact',
                        'admn' => 'admin',
                        'org',
                        'org.'=> 'organization',
                        default => $e[2],
                    }
                    . $e[3];
            },
            $key
        );
        return match ($key) {
            'sponsoring_registrar',
            'registrar' => 'registrar_name',

            'registrant',
            'domain_registrant' => 'registrant_name',

            'whois',
            'whois_server',
            'registrar_whois_server' => 'domain_whois_server',
            'creation_date',
            'domain_created_on' => 'domain_creation_date',
            'update_date',
            'domain_last_updated',
            'last_updated',
            'updated_date'=> 'domain_updated_date',
            'last_update' => 'domain_whois_last_update',
            'expiration_date',
            'domain_expires_on',
            'expires_on',
            'expires_at',
            'expiry_date',
            'registry_expiry_date',
            'registrar_registration_expiration_date' => 'domain_expiration_date',
            'domain_ns',
            'name_server' => 'domain_name_server',
            'dnssec',
            'dns_sec',
            'domain_signing_key',
            'signing_key',
            'dnssecurity',
            'dns_security' => 'domain_dnssec',
            'domain',
            'name' => 'domain_name',

            'registrant_phone_ext' => 'registrant_phone',
            'registrant_fax_ext' => 'registrant_fax',
            'registrar_phone_ext' => 'registrar_phone',
            'registrar_fax_ext' => 'registrar_fax',
            'admin_phone_ext' => 'admin_phone',
            'admin_fax_ext' => 'admin_fax',
            'billing_phone_ext' => 'billing_phone',
            'billing_fax_ext' => 'billing_fax',
            'technical_phone_ext' => 'technical_phone',
            'technical_fax_ext' => 'technical_fax',

            'registry_domain_id' => 'domain_id',
            'registry_admin_id' => 'admin_id',
            'registry_registrant_id' => 'registrant_id',
            'registrar_iana_id' => 'registrar_id',
            'registry_tech_id',
            'registry_technical_id' => 'technical_id',

            'url_of_the_icann_whois_data_problem_reporting_system' => 'url_icann_data_report',

            'contact_name' => 'admin_name',
            'admin_web',
            'admin_page',
            'admin_web_page' => 'admin_url',
            'technical_web',
            'technical_page',
            'technical_web_page' => 'technical_url',
            'registrar_web',
            'registrar_page',
            'registrar_web_page' => 'registrar_url',
            'registrant_web',
            'registrant_page',
            'registrant_web_page' => 'registrant_url',
            default => $key
        };
    }

    protected function filterValue(string $key, string $item): ?string
    {
        if (str_ends_with($key, '_date') && preg_match('~^[1-9][0-9]{3}~', $item)) {
            try {
                $this->timeZone ??= new DateTimeZone('UTC');
                $date = (new DateTimeImmutable($item))->setTimezone($this->timeZone);
                $item = $date->format(DateTimeInterface::RFC3339);
            } catch (Throwable) {
            }
        }
        return match ($key) {
            'domain_name' => idn_to_utf8(strtolower($item))?:strtolower($item),
            default => $item?:null
        };
    }

    protected function parseData(WhoisResult $result) : array
    {
        $array = self::DEFAULT_METADATA;
        $array['whois_server'] = $result->getServer();
        $stop = false;
        $result = strip_tags($result->getData());
        foreach (explode("\n", $result) as $result) {
            $result = trim($result);
            if (str_starts_with($result, '#')) {
                continue;
            }
            // stop end of whois
            if (str_contains($result, '>>>')) {
                if (!str_contains($result, ':')) {
                    break;
                }
                $result = trim($result, '<>');
                if (!preg_match('~Last\s*Update[^:]*:\s*(.+)$~i', $result, $match)) {
                    break;
                }
                $result = 'last_update: '.$match[1];
                $stop = true;
            }

            $result = explode(':', $result, 2);
            // no info key
            if (count($result) !== 2) {
                continue;
            }

            /**
             * @var array<string> $result
             */
            $key = $this->getAliasDataName(array_shift($result));
            $result = trim(array_shift($result));
            $result = $this->filterValue($key, $result);
            if (isset($array[$key])) {
                if (is_array($array[$key])) {
                    if ($result) {
                        $array[$key][] = $result;
                    }
                } else {
                    $array[$key] .= ', '. $result;
                }
            } else {
                $array[$key] = $result;
            }
            if ($stop) {
                break;
            }
        }
        return $array;
    }
}
