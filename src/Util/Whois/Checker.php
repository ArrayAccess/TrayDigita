<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\EmptyArgumentException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Util\Filter\Ip as IpValidator;
use ArrayAccess\TrayDigita\Util\Whois\Util\WhoisDataConversionFactory;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;
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

    private WhoisDataConversionFactory $conversionFactory;

    public function __construct(
        protected ?CacheItemPoolInterface $cache = null
    ) {
    }

    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cache;
    }

    public function getConversionFactory(): WhoisDataConversionFactory
    {
        return $this->conversionFactory ??= new WhoisDataConversionFactory();
    }

    public function setConversionFactory(WhoisDataConversionFactory $conversionFactory): void
    {
        $this->conversionFactory = $conversionFactory;
    }

    public function getRequest(): SocketRequest
    {
        return $this->request ??= new SocketRequest();
    }

    public function setRequest(SocketRequest $request): void
    {
        $this->request = $request;
    }

    protected function fromCacheDomain(
        Domain|Ip $domain,
        string $server,
        ?string $extraCommand
    ) : ?WhoisResult {
        $isDomain = $domain instanceof Domain;
        $domain = $isDomain
            ? $domain->getAsciiName()
            : $domain->getAddress();
        $pool = $this->getCache();
        if (!$domain || !$pool) {
            return null;
        }
        $cacheName = self::CACHE_NAME_PREFIX . sha1(
            "$domain:$server:$extraCommand"
        );
        try {
            $item = $pool->getItem($cacheName);
            $result = $item->get();
            if (!$result instanceof WhoisResult
                || !$result->isValid()
            ) {
                $pool->deleteItem($cacheName);
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return !$isDomain || $domain === $result->getAddress()->getAsciiName()
            ? $result
            : null;
    }

    public function getDomainIpServer(Domain|Ip $address): DomainIpServer
    {
        return new DomainIpServer(
            $this->getRequest(),
            $address,
            $this->getCache()
        );
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
        $server = $data->getServer();
        $extraCommand = $data->getExtraCommand();
        $cacheName = self::CACHE_NAME_PREFIX
            . sha1("$ascii:$server:$extraCommand");
        try {
            $item = $pool->getItem($cacheName);
            $item->set($data);
            $expired = static::DEFAULT_EXPIRED;
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            $expired = !is_int($expired) ? self::DEFAULT_EXPIRED : $expired;
            $item->expiresAfter($expired);
            return $pool->save($item) ? $cacheName : false;
        } catch (Throwable) {
            return false;
        }
    }

    public function whois(
        string|Domain|Ip $address,
        bool $useCache = true,
        ?string $server = null,
        int $timeout = 15
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
            $ipAddress = $ipAddress && str_contains($ipAddress, ':')
                ? (IpValidator::isValidIpv6($address) ? $address : false)
                : $ipAddress;
            if ($ipAddress) {
                $address = new Ip($ipAddress);
            } else {
                $address = new Domain($address);
            }
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
            if ($address->isLocal()) {
                return new WhoisResult(
                    $address,
                    '[Local Domain]',
                    DomainIpServer::DEFAULT_SERVER,
                    null
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
                    DomainIpServer::DEFAULT_SERVER,
                    null
                );
            }
        }

        $isIp = $address instanceof Ip;
        $target = $isIp ? $address->getAddress() : $address->getAsciiName();
        // check
        $server = is_string($server) ? trim($server) : null;
        $server = $server?:null;
        $extraCommand = null;
        if (!$server) {
            $extension = $this->getDomainIpServer($address);
            $server = $extension->getServer();
            $extraCommand = $extension->getExtraCommand();
        }
        if (!$server) {
            throw new RuntimeException(
                sprintf(
                    'Could not determine server from %s: %s',
                    $isIp ? 'IP' : 'domain',
                    $address->getAddress()
                )
            );
        }

        if ($useCache
            && ($result = $this->fromCacheDomain($address, $server, $extraCommand))
        ) {
            return $result;
        }

        $response = $this->getRequest()->doRequest(
            $server,
            $target,
            $timeout,
            $extraCommand
        );
        if ($response['error']['code'] && $response['error']['message']) {
            throw new RuntimeException(
                sprintf('Whois Request Error: %s', $response['error']['message'])
            );
        }
        $result = new WhoisResult(
            $address,
            trim($response['result']??''),
            $server,
            $extraCommand
        );
        $this->saveCache($result);
        return $result;
    }
}
