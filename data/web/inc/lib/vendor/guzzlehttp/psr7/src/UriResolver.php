<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\UriInterface;

/**
 * Resolves a URI reference in the context of a base URI and the opposite way.
 *
 * @author Tobias Schultze
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5
 */
final class UriResolver
{
    /**
     * Removes dot segments from a path and returns the new path according to
     * RFC 3986 Section 5.2.4.
     *
     * Excess `..` segments above the root of an absolute path are dropped
     * without consuming the root, so the result can begin with `//` (e.g.
     * `/..//a` becomes `//a`). Such a path is not valid for a URI without an
     * authority (RFC 3986 Section 3.3); `resolve()` and
     * `UriNormalizer::normalize()` serialize it with a `/.` prefix in that
     * case, like the WHATWG URL Standard.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.4
     */
    public static function removeDotSegments(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $results = [];
        $segments = explode('/', $path);
        // The first segment of an absolute path is the empty root marker producing the
        // leading slash. RFC 3986 Section 5.2.4 (2C) drops ".." segments in excess of
        // the path hierarchy without consuming the root, so it must never be popped.
        $floor = $segments[0] === '' ? 1 : 0;
        foreach ($segments as $segment) {
            if ($segment === '..') {
                if (count($results) > $floor) {
                    array_pop($results);
                }
            } elseif ($segment !== '.') {
                $results[] = $segment;
            }
        }

        $newPath = implode('/', $results);

        if (str_starts_with($path, '/') && !str_starts_with($newPath, '/')) {
            // Re-add the leading slash if necessary for cases like "/.."
            $newPath = '/'.$newPath;
        } elseif ($newPath !== '' && ($segment === '.' || $segment === '..')) {
            // Add the trailing slash if necessary
            // If newPath is not empty, then $segment must be set and is the last segment from the foreach
            $newPath .= '/';
        }

        return $newPath;
    }

    /**
     * Returns the path, prefixed with "/." or "./" when the URI could not
     * otherwise hold it.
     *
     * A URI without an authority cannot hold a path beginning with "//" (RFC
     * 3986 Section 3.3), but removeDotSegments() can produce one. The "/."
     * prefix serializes such a path unambiguously, the same way the WHATWG URL
     * Standard does, and resolves back to the same path. Hostless http and
     * https Uri instances gain the default localhost host when the path is
     * written, so the path cannot be mistaken for an authority and the prefix
     * is not added.
     *
     * A relative-path reference cannot begin with a segment containing a colon
     * (RFC 3986 Section 4.2), as it would be mistaken for a scheme name, but
     * reference resolution and percent-encoding normalization can produce one.
     * The "./" prefix the RFC prescribes resolves back to the same path.
     *
     * @see https://url.spec.whatwg.org/#url-serializing
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     *
     * @internal
     */
    public static function guardedPath(UriInterface $uri, string $path): string
    {
        if ($uri->getAuthority() !== '') {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            if ($uri instanceof Uri && ($uri->getScheme() === 'http' || $uri->getScheme() === 'https')) {
                return $path;
            }

            return '/.'.$path;
        }

        if ($uri->getScheme() === '' && str_contains(explode('/', $path, 2)[0], ':')) {
            return './'.$path;
        }

        return $path;
    }

    /**
     * Converts the relative URI into a new URI that is resolved against the
     * base URI.
     *
     * When the resolved path is a relative-path reference whose first segment
     * contains a colon, which would be mistaken for a scheme name (RFC 3986
     * Section 4.2), it is prefixed with `./`, e.g. `./a:b`.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function resolve(UriInterface $base, UriInterface $rel): UriInterface
    {
        if ((string) $rel === '') {
            // we can simply return the same base URI instance for this same-document reference
            return $base;
        }

        if ($rel->getScheme() != '') {
            return $rel->withPath(self::guardedPath($rel, self::removeDotSegments(Uri::rawPath($rel))));
        }

        if ($rel->getAuthority() != '') {
            return $rel
                ->withScheme($base->getScheme())
                ->withPath(self::removeDotSegments(Uri::rawPath($rel)));
        }

        $relPath = Uri::rawPath($rel);

        if ($relPath === '') {
            // the base path is used as-is per RFC 3986 Section 5.2.2, so it must not be
            // rewritten through a getPath()/withPath() round-trip
            return $base
                ->withQuery($rel->getQuery() != '' ? $rel->getQuery() : $base->getQuery())
                ->withFragment($rel->getFragment());
        }

        if (str_starts_with($relPath, '/')) {
            $targetPath = $relPath;
        } else {
            $basePath = Uri::rawPath($base);
            if ($base->getAuthority() != '' && $basePath === '') {
                $targetPath = '/'.$relPath;
            } else {
                $lastSlashPos = strrpos($basePath, '/');
                if ($lastSlashPos === false) {
                    $targetPath = $relPath;
                } else {
                    $targetPath = substr($basePath, 0, $lastSlashPos + 1).$relPath;
                }
            }
        }
        $targetPath = self::removeDotSegments($targetPath);

        return $base
            ->withPath(self::guardedPath($base, $targetPath))
            ->withQuery($rel->getQuery())
            ->withFragment($rel->getFragment());
    }

    /**
     * Returns the target URI as a relative reference from the base URI.
     *
     * This method is the counterpart to `resolve()`:
     *
     *    (string) $target === (string) UriResolver::resolve($base, UriResolver::relativize($base, $target))
     *
     * One use case is to use the current request URI as the base URI and then
     * generate relative links in your documents to reduce the document size or
     * offer self-contained downloadable document archives.
     *
     *    $base = new Uri('http://example.com/a/b/');
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/b/c'));  // prints 'c'.
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/x/y'));  // prints '../x/y'.
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/b/?q')); // prints '?q'.
     *    echo UriResolver::relativize($base, new Uri('http://example.org/a/b/'));   // prints '//example.org/a/b/'.
     *    echo UriResolver::relativize($base, new Uri('http://example.com'));         // prints '//example.com'.
     *
     * This method also accepts a target that is already relative and will try
     * to relativize it further. Only a relative-path reference will be returned
     * as-is.
     *
     *    echo UriResolver::relativize($base, new Uri('/a/b/c'));  // prints 'c' as well
     */
    public static function relativize(UriInterface $base, UriInterface $target): UriInterface
    {
        if ($target->getScheme() !== ''
            && ($base->getScheme() !== $target->getScheme() || $target->getAuthority() === '' && $base->getAuthority() !== '')
        ) {
            return $target;
        }

        if (Uri::isRelativePathReference($target)) {
            // As the target is already highly relative we return it as-is. It would be possible to resolve
            // the target with `$target = self::resolve($base, $target);` and then try make it more relative
            // by removing a duplicate query. But let's not do that automatically.
            return $target;
        }

        if ($target->getAuthority() !== '' && $base->getAuthority() !== $target->getAuthority()) {
            return $target->withScheme('');
        }

        // A same-authority target with an empty path can only be expressed by a
        // network-path reference (RFC 3986 Section 5.2.2).
        if (self::needsNetworkPathReference($base, $target)) {
            return $target->withScheme('');
        }

        // We must remove the path before removing the authority because if the path starts with two slashes, the URI
        // would turn invalid. And we also cannot set a relative path before removing the authority, as that is also
        // invalid.
        $emptyPathUri = $target->withScheme('')->withPath('')->withUserInfo('')->withPort(null)->withHost('');

        if (Uri::rawPath($base) !== Uri::rawPath($target)) {
            return $emptyPathUri->withPath(self::getRelativePath($base, $target));
        }

        if ($base->getQuery() === $target->getQuery() && ($target->getFragment() !== '' || $base->getFragment() === '')) {
            // Only the target fragment is left. And it must be returned even if base and target fragment are the same.
            return $emptyPathUri->withQuery('');
        }

        // If the base URI has a query or fragment that the target lacks, we cannot return an empty path
        // reference as it would inherit that base component when resolving.
        if ($target->getQuery() === '') {
            $segments = explode('/', Uri::rawPath($target));
            /** @var string $lastSegment */
            $lastSegment = end($segments);

            // A reference to an empty last segment must be prefixed with "./". The same applies
            // to a segment with a colon character, which would be mistaken for a scheme name.
            if ($lastSegment === '' || str_contains($lastSegment, ':')) {
                $lastSegment = "./$lastSegment";
            }

            return $emptyPathUri->withPath($lastSegment);
        }

        return $emptyPathUri;
    }

    /**
     * Whether relativizing to $target requires a network-path reference.
     *
     * A same-authority target with an empty path is expressible by a shorter
     * relative reference unless resolving one would inherit a base component
     * the target lacks: the base path (kept by any empty-path reference), or
     * the base query or fragment (inherited by the empty reference).
     */
    private static function needsNetworkPathReference(UriInterface $base, UriInterface $target): bool
    {
        if ($target->getAuthority() === '' || Uri::rawPath($target) !== '') {
            return false;
        }

        return Uri::rawPath($base) !== ''
            || ($base->getQuery() !== '' && $target->getQuery() === '')
            || ($base->getFragment() !== '' && $target->getFragment() === '' && $base->getQuery() === $target->getQuery());
    }

    private static function getRelativePath(UriInterface $base, UriInterface $target): string
    {
        $sourceSegments = explode('/', Uri::rawPath($base));
        $targetSegments = explode('/', Uri::rawPath($target));
        array_pop($sourceSegments);
        $targetLastSegment = array_pop($targetSegments);
        foreach ($sourceSegments as $i => $segment) {
            if (isset($targetSegments[$i]) && $segment === $targetSegments[$i]) {
                unset($sourceSegments[$i], $targetSegments[$i]);
            } else {
                break;
            }
        }
        $targetSegments[] = $targetLastSegment;
        $relativePath = str_repeat('../', count($sourceSegments)).implode('/', $targetSegments);

        // A reference to am empty last segment or an empty first sub-segment must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name.
        if ($relativePath === '' || str_contains(explode('/', $relativePath, 2)[0], ':')) {
            $relativePath = "./$relativePath";
        } elseif (str_starts_with($relativePath, '/')) {
            if ($base->getAuthority() != '' && Uri::rawPath($base) === '') {
                // In this case an extra slash is added by resolve() automatically. So we must not add one here.
                $relativePath = ".$relativePath";
            } else {
                $relativePath = "./$relativePath";
            }
        }

        return $relativePath;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}
