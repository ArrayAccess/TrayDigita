<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use Psr\Http\Message\UriInterface;
use function array_map;
use function array_pop;
use function end;
use function explode;
use function implode;
use function is_array;
use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function str_repeat;
use function strrpos;
use function substr;
use function urlencode;

/**
 * Resolves a URI reference in the context of a base URI and the opposite way.
 *
 * @author Tobias Schultze
 *
 * @link https://github.com/guzzle/guzzle
 * @link https://tools.ietf.org/html/rfc3986#section-5
 */
final class UriResolver
{
    /**
     * UTF-8 aware \parse_url() replacement.
     *
     * The internal function produces broken output for non ASCII domain names
     * (IDN) when used with locales other than "C".
     *
     * On the other hand, cURL understands IDN correctly only when UTF-8 locale
     * is configured ("C.UTF-8", "en_US.UTF-8", etc.).
     *
     * @see https://bugs.php.net/bug.php?id=52923
     * @see https://www.php.net/manual/en/function.parse-url.php#114817
     * @see https://curl.haxx.se/libcurl/c/CURLOPT_URL.html#ENCODING
     *
     * @return array|false
     */
    public static function parse(string $url) : bool|array
    {
        // If IPv6
        $prefix = '';
        if (preg_match('%^(.*://\[[0-9:a-f]+])(.*?)$%', $url, $matches)) {
            /** @var array{0:string, 1:string, 2:string} $matches */
            $prefix = $matches[1];
            $url = $matches[2];
        }

        /**
         * @var string $encodedUrl
         */
        /** @noinspection PhpRegExpRedundantModifierInspection */
        $encodedUrl = preg_replace_callback(
            '~[^:/@?&=#]+~usD',
            static function ($matches) {
                return urlencode($matches[0]);
            },
            $url
        );

        $result = parse_url($prefix . $encodedUrl);

        if (!is_array($result)) {
            return false;
        }

        return array_map('urldecode', $result);
    }

    /**
     * Removes dot segments from a path and returns the new path.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.4
     */
    public static function removeDotSegments(string $path) : string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $results = [];
        $segments = explode('/', $path);
        $segment = '';
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($results);
            } elseif ($segment !== '.') {
                $results[] = $segment;
            }
        }

        $newPath = implode('/', $results);
        if ($path[0] === '/' && (!isset($newPath[0]) || $newPath[0] !== '/')) {
            $newPath = '/' . $newPath;
        } elseif ($newPath !== '' && ($segment === '.' || $segment === '..')) {
            $newPath .= '/';
        }

        return $newPath;
    }

    /**
     * Converts the relative URI into a new URI that is resolved against the base URI.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-5.2
     */
    public static function resolve(UriInterface $base, UriInterface $rel) : UriInterface
    {
        if ((string) $rel === '') {
            return $base;
        }

        if ($rel->getScheme() !== '') {
            return $rel->withPath(self::removeDotSegments($rel->getPath()));
        }

        if ($rel->getAuthority() !== '') {
            $targetAuthority = $rel->getAuthority();
            $targetPath = self::removeDotSegments($rel->getPath());
            $targetQuery = $rel->getQuery();
        } else {
            $targetAuthority = $base->getAuthority();
            if ($rel->getPath() === '') {
                $targetPath = $base->getPath();
                $targetQuery = $rel->getQuery() !== '' ? $rel->getQuery() : $base->getQuery();
            } else {
                if ($rel->getPath()[0] === '/') {
                    $targetPath = $rel->getPath();
                } else {
                    if ($targetAuthority !== '' && $base->getPath() === '') {
                        $targetPath = '/' . $rel->getPath();
                    } else {
                        $lastSlashPos = strrpos($base->getPath(), '/');
                        if ($lastSlashPos === false) {
                            $targetPath = $rel->getPath();
                        } else {
                            $targetPath = substr(
                                $base->getPath(),
                                0,
                                $lastSlashPos + 1
                            ) . $rel->getPath();
                        }
                    }
                }
                $targetPath = self::removeDotSegments($targetPath);
                $targetQuery = $rel->getQuery();
            }
        }

        return new Uri(Uri::composeComponents(
            $base->getScheme(),
            $targetAuthority,
            $targetPath,
            $targetQuery,
            $rel->getFragment()
        ));
    }

    /**
     * Returns the target URI as a relative reference from the base URI.
     *
     * This method is the counterpart to resolve() :
     *
     *    (string) $target === (string) UriResolver::resolve($base, UriResolver::relativize($base, $target))
     *
     * One use-case is to use the current request URI as base URI and then generate relative links in your documents
     * to reduce the document size or offer self-contained downloadable document archives.
     *
     *    $base = new Uri('http://example.com/a/b/');
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/b/c'));  // prints 'c'.
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/x/y'));  // prints '../x/y'.
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/b/?q')); // prints '?q'.
     *    echo UriResolver::relativize($base, new Uri('http://example.org/a/b/'));   // prints '//example.org/a/b/'.
     *
     * This method also accepts a target that is already relative and will try to relativize it further. Only a
     * relative-path reference will be returned as-is.
     *
     *    echo UriResolver::relativize($base, new Uri('/a/b/c'));  // prints 'c' as well
     * @noinspection PhpUnused
     */
    public static function relativize(UriInterface $base, UriInterface $target) : UriInterface
    {
        if ($target->getScheme() !== '' &&
            (
                $base->getScheme() !== $target->getScheme()
                || $target->getAuthority() === '' && $base->getAuthority() !== ''
            )
        ) {
            return $target;
        }

        if (Uri::isRelativePathReference($target)) {
            return $target;
        }

        if ($target->getAuthority() !== '' && $base->getAuthority() !== $target->getAuthority()) {
            return $target->withScheme('');
        }

        $emptyPathUri = $target
            ->withScheme('')
            ->withPath('')
            ->withUserInfo('')
            ->withPort(null)
            ->withHost('');

        if ($base->getPath() !== $target->getPath()) {
            return $emptyPathUri->withPath(self::getRelativePath($base, $target));
        }

        if ($base->getQuery() === $target->getQuery()) {
            return $emptyPathUri->withQuery('');
        }

        if ($target->getQuery() === '') {
            $segments = explode('/', $target->getPath());
            /** @var string $lastSegment */
            $lastSegment = end($segments);

            return $emptyPathUri->withPath($lastSegment === '' ? './' : $lastSegment);
        }

        return $emptyPathUri;
    }

    private static function getRelativePath(UriInterface $base, UriInterface $target) : string
    {
        $sourceSegments = explode('/', $base->getPath());
        $targetSegments = explode('/', $target->getPath());
        array_pop($sourceSegments);
        $targetLastSegment = array_pop($targetSegments);
        foreach ($sourceSegments as $i => $segment) {
            if (!isset($targetSegments[$i]) || $segment !== $targetSegments[$i]) {
                break;
            }
            unset($sourceSegments[$i], $targetSegments[$i]);
        }

        $targetSegments[] = $targetLastSegment;
        $relativePath = str_repeat('../', count($sourceSegments)) . implode('/', $targetSegments);
        if ('' === $relativePath || str_contains(explode('/', $relativePath, 2)[0], ':')) {
            $relativePath = "./$relativePath";
        } elseif ('/' === $relativePath[0]) {
            if ($base->getAuthority() !== '' && $base->getPath() === '') {
                $relativePath = ".$relativePath";
            } else {
                $relativePath = "./$relativePath";
            }
        }

        return $relativePath;
    }
}
