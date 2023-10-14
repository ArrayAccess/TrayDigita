<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use Psr\Cache\CacheItemPoolInterface;
use function array_pop;
use function explode;
use function is_int;
use function preg_match;
use function sha1;
use function sprintf;
use function trim;

final class DomainIpServer
{
    const CACHE_NAME_PREFIX = 'whois_domain_ip_server_';

    const DEFAULT_EXPIRED = 21600; // 6 hours

    private static array $extensionServers = [];

    const DEFAULT_SERVER = 'whois.iana.org';

    const APNIC = 'APNIC';
    const ARIN = 'ARIN';
    const LACNIC = 'LACNIC';
    const RIPE = 'RIPE';
    const AFRINIC = 'AFRINIC';

    const IP_WHOIS_SERVER_PROVIDER = [
        'whois.apnic.net' => self::APNIC,
        'whois.arin.net' => self::ARIN,
        'whois.ripe.net' => self::RIPE,
        'whois.lacnic.net' => self::LACNIC,
        'whois.afrinic.net' => self::AFRINIC,
    ];

    protected Domain|Ip $address;

    protected string|false|null $extension = null;

    protected ?string $server = null;

    public function __construct(
        protected SocketRequest $request,
        Domain|Ip $address,
        protected ?CacheItemPoolInterface $cache = null
    ) {
        $this->address = $address;
    }

    public function getAddress(): Domain|Ip
    {
        return $this->address;
    }

    protected function getDomainNameExtension()
    {
        if (!$this->address instanceof Domain) {
            return null;
        }
        if (null !== $this->extension) {
            return $this->extension?:null;
        }
        $this->extension = false;
        $asciiDomain = $this->address->getAsciiName();
        if (!$asciiDomain) {
            return null;
        }
        $asciiDomain = explode('.', $asciiDomain);
        $this->extension = array_pop($asciiDomain);
        return $this->extension;
    }

    public function getServer()
    {
        if ($this->server !== null) {
            return $this->server === '' ? null : $this->server;
        }
        $this->server = '';
        if (!$this->address->isValid()) {
            return null;
        }
        if ($this->address instanceof Domain) {
            $extension = $this->getDomainNameExtension();
            $this->server = '';
            if (!$extension) {
                return null;
            }
        } else {
            if ($this->address->isLocal()) {
                return null;
            }
            $extension = $this->address->getAddress();
        }

        if (isset(self::$extensionServers[$extension])) {
            $this->server = self::$extensionServers[$extension];
            return $this->server === '' ? null : $this->server;
        }

        $cacheName = self::CACHE_NAME_PREFIX.sha1($extension);
        $item = $this->cache?->getItem($cacheName);
        $cache = $item?->get();
        if ($cache instanceof WhoisExtensionCache
            && $cache->isValid()
        ) {
            $this->server = $cache->getServer();
            self::$extensionServers[$extension] = $this->server;
            return $this->server;
        }
        $response = $this->request->doRequest(
            self::DEFAULT_SERVER,
            $extension
        );

        if ($response['error']['code'] && $response['error']['message']) {
            throw new RuntimeException(
                sprintf('Server Error: %s', $response['error']['message'])
            );
        }

        preg_match(
            '~^\s*whois\s*:\s*(\S+)\s*$~mi',
            $response['result']??'',
            $match
        );
        $this->server = trim($match[1]??'');
        if ($this->cache && $item) {
            $cache = new WhoisExtensionCache(
                $extension,
                $this->server?:false
            );
            /** @noinspection PhpUnnecessaryStaticReferenceInspection */
            $expired = static::DEFAULT_EXPIRED;
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            $expired = !is_int($expired) ? self::DEFAULT_EXPIRED : $expired;
            $item->set($cache);
            $item->expiresAfter($expired);
            $this->cache->save($item);
        }

        return $this->server;
    }
}
