<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\EmptyArgumentException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Util\Filter\Ip as IpValidator;
use Psr\Cache\CacheItemPoolInterface;
use function is_int;
use function is_object;
use function is_string;
use function sha1;
use function sprintf;
use function trim;

class Checker
{
    const CACHE_NAME_PREFIX = 'whois_domain_ip_data_';

    const DEFAULT_EXPIRED = 21600; // 6 hours

    /**
     * @var SocketRequest
     */
    private SocketRequest $request;

    public function __construct(
        protected ?CacheItemPoolInterface $cache = null
    ) {
        $this->setRequest(new SocketRequest());
    }

    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cache;
    }

    public function getRequest(): SocketRequest
    {
        return $this->request;
    }

    public function setRequest(SocketRequest $request): void
    {
        $this->request = $request;
    }

    protected function fromCacheDomain(Domain|Ip $domain) : ?WhoisResult
    {
        $isDomain = $domain instanceof Domain;
        $domain = $isDomain
            ? $domain->getAsciiName()
            : $domain->getAddress();
        $pool = $this->getCache();
        if (!$domain || !$pool) {
            return null;
        }
        $cacheName = self::CACHE_NAME_PREFIX . sha1($domain);
        $item = $pool->getItem($cacheName);
        $result = $item->get();
        if (!$result instanceof WhoisResult
            || ! $result->isValid()
        ) {
            $pool->deleteItem($cacheName);
            return null;
        }

        return !$isDomain || $domain === $result->getAddress()->getAsciiName()
            ? $result
            : null;
    }

    protected function saveCache(WhoisResult $data) : string|false
    {
        $pool = $this->getCache();
        if (!$pool) {
            return false;
        }
        $domain = $data->getAddress();
        $ascii  = $data->isIp() ? $domain->getAddress() : $domain->getAsciiName();
        if (!$ascii) {
            return false;
        }
        $cacheName = self::CACHE_NAME_PREFIX . sha1($ascii);
        $item = $pool->getItem($cacheName);
        $item->set($data);
        $expired = static::DEFAULT_EXPIRED;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $expired = !is_int($expired) ? self::DEFAULT_EXPIRED : $expired;
        $item->expiresAfter($expired);
        return $pool->save($item) ? $cacheName : false;
    }

    public function whois(
        string|Domain|Ip $address,
        bool $useCache = true
    ): WhoisResult {
        $address = is_string($address) ? trim($address) : $address;
        if ($address === '' || is_object($address) && $address->getAddress() === '') {
            throw new EmptyArgumentException(
                'Domain name or IP could not be empty'
            );
        }

        if (is_string($address)) {
            // filter ip
            $ipAddress = IpValidator::filterIpv4($address);
            // if not ip4 -> check ipv6
            $ipAddress = !$ipAddress && str_contains($ipAddress, ':')
                ? (IpValidator::isValidIpv6($address) ? $address : false)
                : $ipAddress;
            if ($ipAddress) {
                $address = new Ip($ipAddress);
            } else {
                $address = new Domain($address);
            }
        }

        if ($useCache
            && ($result = $this->fromCacheDomain($address))
        ) {
            return $result;
        }

        if ($address instanceof Domain) {
            $asciiName = $address->getAsciiName();
            if (!$asciiName) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Domain name "%s" is invalid',
                        $address->getAddress()
                    )
                );
            }
        } else {
            if (!$address->isValid()) {
                throw new InvalidArgumentException(
                    sprintf(
                        'IP address "%s" is invalid',
                        $address->getAddress()
                    )
                );
            }
            if ($address->isLocal()) {
                return new WhoisResult(
                    $address,
                    '[Local IP]',
                    DomainIpServer::DEFAULT_SERVER
                );
            }
        }

        $isIp = $address instanceof Ip;
        $target = $isIp ? $address->getAddress() : $address->getAsciiName();
        $extension = new DomainIpServer(
            $this->getRequest(),
            $address,
            $this->getCache()
        );

        $server = $extension->getServer();
        if (!$server) {
            throw new RuntimeException(
                sprintf(
                    'Could not determine server from %s: %s',
                    $isIp ? 'IP' : 'domain',
                    $address->getAddress()
                )
            );
        }

        $response = $this->getRequest()->doRequest($server, $target);
        if ($response['error']['code'] && $response['error']['message']) {
            throw new RuntimeException(
                sprintf('Whois Request Error: %s', $response['error']['message'])
            );
        }
        $result = new WhoisResult($address, trim($response['result']??''), $server);
        $this->saveCache($result);
        return $result;
    }
}
