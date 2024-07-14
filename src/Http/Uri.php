<?php
/** @noinspection RegExpDuplicateCharacterInClass */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Http\Exceptions\MalformedUriException;
use JsonSerializable;
use Psr\Http\Message\UriInterface;
use function array_filter;
use function array_keys;
use function array_map;
use function explode;
use function get_object_vars;
use function implode;
use function in_array;
use function is_string;
use function parse_url;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function strtr;

class Uri implements UriInterface, JsonSerializable
{
    /**
     * Absolute http and https URIs require a host per RFC 7230 Section 2.7
     * but in generic URIs the host can be empty. So for http(s) URIs
     * we apply this default host when no host is given yet to form a
     * valid URI.
     */
    private const HTTP_DEFAULT_HOST = 'localhost';

    private const DEFAULT_PORTS = [
        'http'  => 80,
        'https' => 443,
        'ftp' => 21,
        'ftps' => 990,
        'gopher' => 70,
        'nntp' => 119,
        'news' => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
    ];

    /**
     * Unreserved characters for use in a regex.
     *
     * @link https://tools.ietf.org/html/rfc3986#section-2.3
     */
    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-.~';

    /**
     * Sub-delimiter for use in a regex.
     *
     * @link https://tools.ietf.org/html/rfc3986#section-2.2
     */
    private const CHAR_SUB_DELIMITERS = '!$&\'()*+,;=';

    private const QUERY_SEPARATORS_REPLACEMENT = ['=' => '%3D', '&' => '%26'];

    /** @var string Uri scheme. */
    private string $scheme = '';

    /** @var string Uri user info. */
    private string $userInfo = '';

    /** @var string Uri host. */
    private string $host = '';

    /** @var ?int Uri port. */
    private ?int $port = null;

    /** @var string Uri path. */
    private string $path = '';

    /** @var string Uri query string. */
    private string $query = '';

    /** @var string Uri fragment. */
    private string $fragment = '';

    /** @var string|null String representation */
    private ?string $composedComponents = null;

    public function __construct(string|UriInterface $uri = '')
    {
        if ($uri instanceof UriInterface) {
            $this->port = $uri->getPort();
            $this->path = $uri->getPath();
            $this->query = $uri->getQuery();
            $this->fragment = $uri->getFragment();
            $this->userInfo = $uri->getUserInfo();
            $this->scheme = $uri->getScheme();
            return;
        }

        if ($uri !== '') {
            $parts = UriResolver::parse($uri);
            if ($parts === false) {
                throw new MalformedUriException("Unable to parse URI: $uri");
            }
            $this->applyParts($parts);
        }
    }

    /**
     * @param string $url
     *
     * @return UriInterface
     */
    public static function fromUrlString(string $url) : UriInterface
    {
        return new Uri($url);
    }

    private static function extractHostAndPortFromAuthority(string $authority) : array
    {
        $uri   = 'https://' . $authority;
        $parts = parse_url($uri);
        if (false === $parts) {
            return [null, null];
        }

        $host = $parts['host'] ?? null;
        $port = $parts['port'] ?? null;

        return [$host, $port];
    }

    public static function fromGlobals() : UriInterface
    {
        $uri = new Uri('');
        $uri = $uri->withScheme(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');

        $hasPort = false;
        if (isset($_SERVER['HTTP_HOST'])) {
            [$host, $port] = self::extractHostAndPortFromAuthority($_SERVER['HTTP_HOST']);
            if ($host !== null) {
                $uri = $uri->withHost($host);
            }

            if ($port !== null) {
                $hasPort = true;
                $uri = $uri->withPort($port);
            }
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $uri = $uri->withHost($_SERVER['SERVER_NAME']);
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $uri = $uri->withHost($_SERVER['SERVER_ADDR']);
        }

        if (!$hasPort && isset($_SERVER['SERVER_PORT'])) {
            $uri = $uri->withPort($_SERVER['SERVER_PORT']);
        }

        $hasQuery = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            $requestUriParts = explode('?', $_SERVER['REQUEST_URI'], 2);
            $uri = $uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $uri = $uri->withQuery($requestUriParts[1]);
            }
        }

        if (!$hasQuery && isset($_SERVER['QUERY_STRING'])) {
            $uri = $uri->withQuery($_SERVER['QUERY_STRING']);
        }

        return $uri;
    }

    public function __toString() : string
    {
        if ($this->composedComponents === null) {
            $this->composedComponents = self::composeComponents(
                $this->scheme,
                $this->getAuthority(),
                $this->path,
                $this->query,
                $this->fragment
            );
        }

        return $this->composedComponents;
    }

    /**
     * Composes a URI reference string from its various components.
     *
     * Usually this method does not need to be called manually but instead is used indirectly via
     * `Psr\Http\Message\UriInterface::__toString`.
     *
     * PSR-7 UriInterface treats an empty component the same as a missing component as
     * getQuery(), getFragment() etc. always return a string. This explains the slight
     * difference to RFC 3986 Section 5.3.
     *
     * Another adjustment is that the authority separator is added even when the authority is missing/empty
     * for the "file" scheme. This is because PHP stream functions like `file_get_contents` only work with
     * `file:///my_file` but not with `file:/my_file` although they are equivalent according to RFC 3986. But
     * `file:///` is the more common syntax for the file scheme anyway (Chrome for example redirects to
     * that format).
     *
     * @link https://tools.ietf.org/html/rfc3986#section-5.3
     */
    public static function composeComponents(
        ?string $scheme,
        ?string $authority,
        string $path,
        ?string $query,
        ?string $fragment
    ) : string {
        $uri = '';

        // weak type checks to also accept null until we can add scalar type hints
        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }

        if ($authority !== '' || $scheme === 'file') {
            $uri .= '//' . $authority;
        }

        if ($authority !== '' && $path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        $uri .= $path;

        if ($query !== '') {
            $uri .= '?' . $query;
        }

        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Whether the URI has the default port of the current scheme.
     *
     * `Psr\Http\Message\UriInterface::getPort` may return null or the standard port. This method can be used
     * independently of the implementation.
     */
    public static function isDefaultPort(UriInterface $uri) : bool
    {
        return $uri->getPort() === null
            || (isset(self::DEFAULT_PORTS[$uri->getScheme()])
                && $uri->getPort() === self::DEFAULT_PORTS[$uri->getScheme()]
            );
    }

    /**
     * Whether the URI is absolute, i.e. it has a scheme.
     *
     * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
     * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
     * to another URI, the base URI. Relative references can be divided into several forms:
     * - network-path references, e.g. '//example.com/path'
     * - absolute-path references, e.g. '/path'
     * - relative-path references, e.g. 'sub path'
     *
     * @see Uri::isNetworkPathReference
     * @see Uri::isAbsolutePathReference
     * @see Uri::isRelativePathReference
     * @link https://tools.ietf.org/html/rfc3986#section-4
     */
    public static function isAbsolute(UriInterface $uri) : bool
    {
        return $uri->getScheme() !== '';
    }

    /**
     * Whether the URI is a network-path reference.
     *
     * A relative reference that begins with two slash characters is termed a network-path reference.
     *
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isNetworkPathReference(UriInterface $uri) : bool
    {
        return $uri->getScheme() === '' && $uri->getAuthority() !== '';
    }

    /**
     * Whether the URI is an absolute-path reference.
     *
     * A relative reference that begins with a single slash character is termed an absolute-path reference.
     *
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isAbsolutePathReference(UriInterface $uri) : bool
    {
        return $uri->getScheme() === ''
            && $uri->getAuthority() === ''
            && isset($uri->getPath()[0])
            && $uri->getPath()[0] === '/';
    }

    /**
     * Whether the URI is a relative-path reference.
     *
     * A relative reference that does not begin with a slash character is termed a relative-path reference.
     *
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isRelativePathReference(UriInterface $uri) : bool
    {
        return $uri->getScheme() === ''
            && $uri->getAuthority() === ''
            && (!isset($uri->getPath()[0]) || $uri->getPath()[0] !== '/');
    }

    /**
     * Whether the URI is a same-document reference.
     *
     * A same-document reference refers to a URI that is, aside from its fragment
     * component, identical to the base URI. When no base URI is given, only an empty
     * URI reference (apart from its fragment) is considered a same-document reference.
     *
     * @param UriInterface      $uri  The URI to check
     * @param UriInterface|null $base An optional base URI to compare against
     *
     * @link https://tools.ietf.org/html/rfc3986#section-4.4
     */
    public static function isSameDocumentReference(UriInterface $uri, UriInterface $base = null) : bool
    {
        if ($base !== null) {
            $uri = UriResolver::resolve($base, $uri);

            return ($uri->getScheme() === $base->getScheme())
                && ($uri->getAuthority() === $base->getAuthority())
                && ($uri->getPath() === $base->getPath())
                && ($uri->getQuery() === $base->getQuery());
        }

        return $uri->getScheme() === ''
            && $uri->getAuthority() === ''
            && $uri->getPath() === ''
            && $uri->getQuery() === '';
    }

    /**
     * Creates a new URI with a specific query string value removed.
     *
     * Any existing query string values that exactly match the provided key are
     * removed.
     *
     * @param UriInterface $uri URI to use as a base.
     * @param string       $key Query string key to remove.
     */
    public static function withoutQueryValue(UriInterface $uri, string $key) : UriInterface
    {
        $result = self::getFilteredQueryString($uri, [$key]);

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Creates a new URI with a specific query string value.
     *
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair.
     *
     * A value of null will set the query string key without a value, e.g. "key"
     * instead of "key=value".
     *
     * @param UriInterface $uri   URI to use as a base.
     * @param string       $key   Key to set.
     * @param string|null  $value Value to set
     */
    public static function withQueryValue(UriInterface $uri, string $key, ?string $value) : UriInterface
    {
        $result = self::getFilteredQueryString($uri, [$key]);

        $result[] = self::generateQueryString($key, $value);

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Creates a new URI with multiple specific query string values.
     *
     * It has the same behavior as withQueryValue() but for an associative array of key => value.
     *
     * @param UriInterface               $uri           URI to use as a base.
     * @param array<string, string|null> $keyValueArray Associative array of key and values
     */
    public static function withQueryValues(UriInterface $uri, array $keyValueArray) : UriInterface
    {
        $result = self::getFilteredQueryString($uri, array_keys($keyValueArray));

        foreach ($keyValueArray as $key => $value) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $result[] = self::generateQueryString(
                (string) $key,
                $value !== null ? (string) $value : null
            );
        }

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Creates a URI from a hash of `parse_url` components.
     *
     * @link http://php.net/manual/en/function.parse-url.php
     *
     * @throws MalformedUriException If the components do not form a valid URI.
     */
    public static function fromParts(array $parts) : UriInterface
    {
        $uri = new self();
        $uri->applyParts($parts);
        $uri->validateState();

        return $uri;
    }

    public function getScheme() : string
    {
        return $this->scheme;
    }

    public function getAuthority() : string
    {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo() : string
    {
        return $this->userInfo;
    }

    public function getHost() : string
    {
        return $this->host;
    }

    public function getPort() : ?int
    {
        return $this->port;
    }

    public function getPath() : string
    {
        return $this->path;
    }

    public function getQuery() : string
    {
        return $this->query;
    }

    public function getFragment() : string
    {
        return $this->fragment;
    }

    public function withScheme($scheme) : UriInterface
    {
        $scheme = $this->filterScheme($scheme);

        if ($this->scheme === $scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->composedComponents = null;
        $new->removeDefaultPort();
        $new->validateState();

        return $new;
    }

    public function withUserInfo($user, $password = null) : UriInterface
    {
        $info = $this->filterUserInfoComponent($user);
        if ($password !== null) {
            $info .= ':' . $this->filterUserInfoComponent($password);
        }

        if ($this->userInfo === $info) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;
        $new->composedComponents = null;
        $new->validateState();

        return $new;
    }

    public function withHost($host) : UriInterface
    {
        $host = $this->filterHost($host);

        if ($this->host === $host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;
        $new->composedComponents = null;
        $new->validateState();

        return $new;
    }

    public function withPort($port) : UriInterface
    {
        $port = $this->filterPort($port);

        if ($this->port === $port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;
        $new->composedComponents = null;
        $new->removeDefaultPort();
        $new->validateState();

        return $new;
    }

    public function withPath($path) : UriInterface
    {
        $path = $this->filterPath($path);

        if ($this->path === $path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;
        $new->composedComponents = null;
        $new->validateState();

        return $new;
    }

    public function withQuery($query) : UriInterface
    {
        $query = $this->filterQueryAndFragment($query);

        if ($this->query === $query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;
        $new->composedComponents = null;

        return $new;
    }

    public function withFragment($fragment) : UriInterface
    {
        $fragment = $this->filterQueryAndFragment($fragment);

        if ($this->fragment === $fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;
        $new->composedComponents = null;

        return $new;
    }

    public function jsonSerialize() : string
    {
        return $this->__toString();
    }

    /**
     * Apply parse_url parts to a URI.
     *
     * @param array $parts Array of parse_url parts to apply.
     */
    private function applyParts(array $parts) : void
    {
        $this->scheme = isset($parts['scheme'])
            ? $this->filterScheme($parts['scheme'])
            : '';
        $this->userInfo = isset($parts['user'])
            ? $this->filterUserInfoComponent($parts['user'])
            : '';
        $this->host = isset($parts['host'])
            ? $this->filterHost($parts['host'])
            : '';
        $this->port = isset($parts['port'])
            ? $this->filterPort($parts['port'])
            : null;
        $this->path = isset($parts['path'])
            ? $this->filterPath($parts['path'])
            : '';
        $this->query = isset($parts['query'])
            ? $this->filterQueryAndFragment($parts['query'])
            : '';
        $this->fragment = isset($parts['fragment'])
            ? $this->filterQueryAndFragment($parts['fragment'])
            : '';
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $this->filterUserInfoComponent($parts['pass']);
        }

        $this->removeDefaultPort();
    }

    /**
     * @param mixed $scheme
     *
     * @return string
     */
    private function filterScheme(mixed $scheme) : string
    {
        if (!is_string($scheme)) {
            throw new InvalidArgumentException('Scheme must be a string');
        }

        return strtr($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * @param mixed $component
     * @return string
     *
     * @throws InvalidArgumentException If the user info is invalid.
     */
    private function filterUserInfoComponent(mixed $component) : string
    {
        if (!is_string($component)) {
            throw new InvalidArgumentException('User info must be a string');
        }

        return preg_replace_callback(
            '#[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMITERS . ']+|%(?![A-Fa-f0-9]{2})#',
            [$this, 'rawUrlEncodeMatchZero'],
            $component
        );
    }

    /**
     * @param mixed $host
     *
     * @return string
     * @throws InvalidArgumentException If the host is invalid.
     */
    private function filterHost(mixed $host) : string
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException('Host must be a string');
        }

        return strtr($host, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * @param mixed $port
     * @return ?int
     * @throws InvalidArgumentException If the port is invalid.
     */
    private function filterPort(mixed $port) : ?int
    {
        if ($port === null) {
            return null;
        }

        $port = (int) $port;
        if (0 > $port || 0xffff < $port) {
            throw new InvalidArgumentException(
                sprintf('Invalid port: %d. Must be between 0 and 65535', $port)
            );
        }

        return $port;
    }

    /**
     * @param string[] $keys
     *
     * @return string[]
     */
    private static function getFilteredQueryString(UriInterface $uri, array $keys) : array
    {
        $current = $uri->getQuery();

        if ($current === '') {
            return [];
        }

        $decodedKeys = array_map('rawurldecode', $keys);

        return array_filter(explode('&', $current), static function ($part) use ($decodedKeys) {
            return !in_array(rawurldecode(explode('=', $part)[0]), $decodedKeys, true);
        });
    }

    private static function generateQueryString(string $key, ?string $value) : string
    {
        // Query string separators ("=", "&") within the key or value need to be encoded
        // (while preventing double-encoding) before setting the query string. All other
        // chars that need percent-encoding will be encoded by withQuery().
        $queryString = strtr($key, self::QUERY_SEPARATORS_REPLACEMENT);

        if ($value !== null) {
            $queryString .= '=' . strtr($value, self::QUERY_SEPARATORS_REPLACEMENT);
        }

        return $queryString;
    }

    private function removeDefaultPort() : void
    {
        if ($this->port !== null && self::isDefaultPort($this)) {
            $this->port = null;
        }
    }

    /**
     * Filter the path of a URI
     *
     * @param mixed $path
     * @return string
     * @throws InvalidArgumentException If the path is invalid.
     */
    private function filterPath(mixed $path) : string
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Path must be a string');
        }

        $sub = self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMITERS;
        return preg_replace_callback(
            "#[^%$sub:@/]++|%(?![A-Fa-f0-9]{2})#",
            [$this, 'rawUrlEncodeMatchZero'],
            $path
        );
    }

    /**
     * Filter the query string or fragment of a URI.
     *
     * @param mixed $str
     * @return string
     * @throws InvalidArgumentException If the query or fragment is invalid.
     */
    private function filterQueryAndFragment(mixed $str) : string
    {
        if (!is_string($str)) {
            throw new InvalidArgumentException('Query and fragment must be a string');
        }

        return preg_replace_callback(
            '#[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMITERS . ':@/?]++|%(?![A-Fa-f0-9]{2})#',
            [$this, 'rawUrlEncodeMatchZero'],
            $str
        );
    }

    private function rawUrlEncodeMatchZero(array $match) : string
    {
        return rawurlencode($match[0]);
    }

    private function validateState() : void
    {
        if ($this->host === '' && ($this->scheme === 'http' || $this->scheme === 'https')) {
            $this->host = self::HTTP_DEFAULT_HOST;
        }

        if ($this->getAuthority() === '') {
            if (str_starts_with($this->path, '//')) {
                throw new MalformedUriException(
                    'The path of a URI without an authority must not start with two slashes "//"'
                );
            }

            if ($this->scheme === '' && str_contains(explode('/', $this->path, 2)[0], ':')) {
                throw new MalformedUriException(
                    'A relative URI must not have a path beginning with a segment containing a colon'
                );
            }
        }
    }

    public function __debugInfo(): ?array
    {
        $info = get_object_vars($this);
        $info['userInfo'] = '<redacted>';
        return $info;
    }
}
