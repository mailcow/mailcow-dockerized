<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 URI implementation.
 *
 * @author Michael Dowling
 * @author Tobias Schultze
 * @author Matthew Weier O'Phinney
 */
class Uri implements UriInterface, \JsonSerializable
{
    /**
     * Absolute http and https URIs require a host per RFC 9110 Section 4.2.1
     * but in generic URIs the host can be empty. So for http(s) URIs we apply
     * this default host when no host is given yet to form a valid URI.
     */
    private const HTTP_DEFAULT_HOST = 'localhost';

    private const DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'gopher' => 70,
        'nntp' => 119,
        'news' => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
        'ws' => 80,
        'wss' => 443,
    ];

    private const QUERY_SEPARATORS_REPLACEMENT = ['=' => '%3D', '&' => '%26', '+' => '%2B'];

    /** @var string Uri scheme. */
    private string $scheme = '';

    /** @var string Uri user info. */
    private string $userInfo = '';

    /** @var string Uri host. */
    private string $host = '';

    /** @var int|null Uri port. */
    private ?int $port = null;

    /** @var string Uri path. */
    private string $path = '';

    /** @var string Uri query string. */
    private string $query = '';

    /** @var string Uri fragment. */
    private string $fragment = '';

    public function __construct(
        #[\SensitiveParameter]
        string $uri = ''
    ) {
        if ($uri !== '') {
            $parts = UriParser::parse($uri);
            if ($parts === false) {
                throw new MalformedUriException(\sprintf('Unable to parse URI: %s', Utils::redactUriStringForMessage($uri)));
            }
            try {
                $this->applyParts($parts);
            } catch (MalformedUriException $e) {
                throw $e;
            } catch (\InvalidArgumentException $e) {
                throw new MalformedUriException($e->getMessage(), 0, $e);
            }
        }
    }

    public function __toString(): string
    {
        return self::composeComponents(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    /**
     * Composes a URI reference string from its various components according to
     * RFC 3986 Section 5.3.
     *
     * Usually this method does not need to be called manually but instead is
     * used indirectly via `Psr\Http\Message\UriInterface::__toString`.
     *
     * PSR-7 UriInterface treats an empty component the same as a missing
     * component as `getQuery()`, `getFragment()` etc. always return a string.
     * This explains the slight difference to RFC 3986 Section 5.3.
     *
     * Another adjustment is that the authority separator is added even when the
     * authority is missing/empty for the "file" scheme. This is because PHP
     * stream functions like `file_get_contents` only work with `file:///myfile`
     * but not with `file:/myfile` although they are equivalent according to RFC
     * 3986. But `file:///` is the more common syntax for the file scheme anyway
     * (Chrome for example redirects to that format). The separator is omitted
     * when such a URI has a rootless or empty path: adding it would turn the
     * first path segment into the authority of the composed URI, or compose the
     * string `file://`, which cannot be parsed back into a URI.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.3
     */
    public static function composeComponents(?string $scheme, ?string $authority, string $path, ?string $query, ?string $fragment): string
    {
        $uri = '';

        // weak type checks to also accept null until we can add scalar type hints
        if ($scheme != '') {
            $uri .= $scheme.':';
        }

        if ($authority != '' || ($scheme === 'file' && str_starts_with($path, '/'))) {
            $uri .= '//'.$authority;
        }

        if ($authority != '' && $path != '' && !str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $uri .= $path;

        if ($query != '') {
            $uri .= '?'.$query;
        }

        if ($fragment != '') {
            $uri .= '#'.$fragment;
        }

        return $uri;
    }

    /**
     * Whether the URI has the default port of the current scheme.
     *
     * `Psr\Http\Message\UriInterface::getPort` may return null or the standard
     * port. This method can be used independently of the implementation.
     */
    public static function isDefaultPort(UriInterface $uri): bool
    {
        return $uri->getPort() === null
            || (isset(self::DEFAULT_PORTS[$uri->getScheme()]) && $uri->getPort() === self::DEFAULT_PORTS[$uri->getScheme()]);
    }

    /**
     * Whether the URI is absolute, i.e. it has a scheme.
     *
     * An instance of UriInterface can either be an absolute URI or a relative
     * reference. An absolute URI has a scheme. A relative reference is used to
     * express a URI relative to another URI, the base URI. Relative references
     * can be divided into several forms according to RFC 3986 Section 4.2:
     * - network-path references, e.g. `//example.com/path`
     * - absolute-path references, e.g. `/path`
     * - relative-path references, e.g. `subpath`
     *
     * @see Uri::isNetworkPathReference
     * @see Uri::isAbsolutePathReference
     * @see Uri::isRelativePathReference
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function isAbsolute(UriInterface $uri): bool
    {
        return $uri->getScheme() !== '';
    }

    /**
     * Whether the URI is a network-path reference.
     *
     * A relative reference that begins with two slash characters is termed a
     * network-path reference.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function isNetworkPathReference(UriInterface $uri): bool
    {
        return $uri->getScheme() === '' && $uri->getAuthority() !== '';
    }

    /**
     * Whether the URI is an absolute-path reference.
     *
     * A relative reference that begins with a single slash character is termed
     * an absolute-path reference.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function isAbsolutePathReference(UriInterface $uri): bool
    {
        return $uri->getScheme() === ''
            && $uri->getAuthority() === ''
            && isset($uri->getPath()[0])
            && $uri->getPath()[0] === '/';
    }

    /**
     * Whether the URI is a relative-path reference.
     *
     * A relative reference that does not begin with a slash character is termed
     * a relative-path reference.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function isRelativePathReference(UriInterface $uri): bool
    {
        return $uri->getScheme() === ''
            && $uri->getAuthority() === ''
            && (!isset($uri->getPath()[0]) || $uri->getPath()[0] !== '/');
    }

    /**
     * Whether the URI is a same-document reference.
     *
     * A same-document reference refers to a URI that is, aside from its
     * fragment component, identical to the base URI. When no base URI is given,
     * only an empty URI reference (apart from its fragment) is considered a
     * same-document reference.
     *
     * @param UriInterface      $uri  The URI to check
     * @param UriInterface|null $base An optional base URI to compare against
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.4
     */
    public static function isSameDocumentReference(UriInterface $uri, ?UriInterface $base = null): bool
    {
        if ($base !== null) {
            $uri = UriResolver::resolve($base, $uri);

            return ($uri->getScheme() === $base->getScheme())
                && ($uri->getAuthority() === $base->getAuthority())
                && (self::rawPath($uri) === self::rawPath($base))
                && ($uri->getQuery() === $base->getQuery());
        }

        return $uri->getScheme() === '' && $uri->getAuthority() === '' && $uri->getPath() === '' && $uri->getQuery() === '';
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
    public static function withoutQueryValue(UriInterface $uri, string $key): UriInterface
    {
        $result = self::getFilteredQueryString($uri, [$key]);

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Creates a new URI with a specific query string value.
     *
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair. A value of null will
     * set the query string key without a value, e.g. "key" instead of
     * "key=value".
     *
     * @param UriInterface $uri   URI to use as a base.
     * @param string       $key   Key to set.
     * @param string|null  $value Value to set
     */
    public static function withQueryValue(UriInterface $uri, string $key, ?string $value): UriInterface
    {
        $result = self::getFilteredQueryString($uri, [$key]);

        $result[] = self::generateQueryString($key, $value);

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Creates a new URI with multiple query string values.
     *
     * It has the same behavior as `withQueryValue()` but for an associative
     * array of key => value.
     *
     * @param UriInterface    $uri           URI to use as a base.
     * @param (string|null)[] $keyValueArray Associative array of key and values
     */
    public static function withQueryValues(UriInterface $uri, array $keyValueArray): UriInterface
    {
        $result = self::getFilteredQueryString($uri, array_keys($keyValueArray));

        foreach ($keyValueArray as $key => $value) {
            self::assertStringOrNullQueryValue($value);
            $result[] = self::generateQueryString((string) $key, $value !== null ? (string) $value : null);
        }

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * @param mixed $value
     */
    private static function assertStringOrNullQueryValue($value): void
    {
        if ($value !== null && !is_string($value)) {
            throw new \InvalidArgumentException(\sprintf(
                'Query string values must be a string or null, %s given.',
                \get_debug_type($value)
            ));
        }
    }

    /**
     * Creates a URI from a hash of `parse_url` components.
     *
     * @see https://www.php.net/manual/en/function.parse-url.php
     *
     * @throws MalformedUriException If the components do not form a valid URI.
     */
    public static function fromParts(
        #[\SensitiveParameter]
        array $parts
    ): UriInterface {
        $uri = new self();
        try {
            $uri->applyParts($parts);
            $uri->validateState();
        } catch (MalformedUriException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw new MalformedUriException($e->getMessage(), 0, $e);
        }

        return $uri;
    }

    /**
     * @throws \InvalidArgumentException If the host is invalid.
     *
     * @internal
     */
    public static function assertValidHost(string $host): void
    {
        if (!Rfc3986::isValidHost($host)) {
            throw new \InvalidArgumentException(sprintf('Invalid host: %s', DiagnosticValue::escape($host)));
        }
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo.'@'.$authority;
        }

        if ($this->port !== null) {
            $authority .= ':'.$this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        if (str_starts_with($this->path, '//')) {
            return '/'.ltrim($this->path, '/');
        }

        return $this->path;
    }

    /**
     * Returns the path as it appears within a URI's string form.
     *
     * getPath() collapses multiple leading slashes so that a path used in
     * isolation cannot be mistaken for a protocol-relative URL. Whole-URI
     * operations like reference resolution and normalization (RFC 3986
     * Sections 5 and 6) are defined on the URI string form, where the path
     * stays verbatim, so they must read the path through this method instead.
     * For direct instances of this class the path is derived from the stored
     * components, including the leading slash the string form adds to a
     * rootless path when an authority is present; for subclasses and other
     * implementations the path is split from the string form per RFC 3986
     * Appendix B, without validating or decoding any other component. The
     * scheme is only split off when the instance reports one, as a relative
     * reference can begin with a segment containing a colon that the Appendix
     * B expression would otherwise read as a scheme.
     *
     * @throws \RuntimeException If the path cannot be split from the string form.
     *
     * @internal
     */
    public static function rawPath(UriInterface $uri): string
    {
        if (get_class($uri) === self::class) {
            if ($uri->path !== '' && !str_starts_with($uri->path, '/') && $uri->getAuthority() !== '') {
                // composeComponents() prepends a slash to a rootless path when
                // an authority is present, so the string form uses this path.
                return '/'.$uri->path;
            }

            return $uri->path;
        }

        $pattern = $uri->getScheme() === ''
            ? '%^(?://[^/?#]*)?([^?#]*)%'
            : '%^(?:[^:/?#]+:)?(?://[^/?#]*)?([^?#]*)%';
        $count = preg_match($pattern, (string) $uri, $matches);

        if ($count === false) {
            throw new \RuntimeException('Unable to read the URI path: '.preg_last_error_msg());
        }

        return $matches[1] ?? '';
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): UriInterface
    {
        $scheme = $this->filterScheme($scheme);

        if ($this->scheme === $scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->removeDefaultPort();
        $new->validateState();

        return $new;
    }

    public function withUserInfo(
        string $user,
        #[\SensitiveParameter]
        ?string $password = null
    ): UriInterface {
        $info = $this->filterUserInfoComponent($user);
        if ($password !== null) {
            $info .= ':'.$this->filterUserInfoComponent($password);
        }

        if ($this->userInfo === $info) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;
        $new->validateState();

        return $new;
    }

    public function withHost(string $host): UriInterface
    {
        $host = $this->filterHost($host);

        if ($this->host === $host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;
        $new->validateState();

        return $new;
    }

    public function withPort(?int $port): UriInterface
    {
        $port = $this->filterPort($port);

        if ($this->port === $port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;
        $new->removeDefaultPort();
        $new->validateState();

        return $new;
    }

    public function withPath(string $path): UriInterface
    {
        $path = $this->filterPath($path);

        if ($this->path === $path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;
        $new->validateState();

        return $new;
    }

    public function withQuery(string $query): UriInterface
    {
        $query = $this->filterQueryAndFragment($query);

        if ($this->query === $query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    public function withFragment(string $fragment): UriInterface
    {
        $fragment = $this->filterQueryAndFragment($fragment);

        if ($this->fragment === $fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    /**
     * Apply parse_url parts to a URI.
     *
     * @param array $parts Array of parse_url parts to apply.
     */
    private function applyParts(
        #[\SensitiveParameter]
        array $parts
    ): void {
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
            ? $this->filterPortPart($parts['port'])
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
            $this->userInfo .= ':'.$this->filterUserInfoComponent($parts['pass']);
        }

        $this->removeDefaultPort();
    }

    /**
     * @throws \InvalidArgumentException If the scheme is invalid.
     */
    private function filterScheme(string $scheme): string
    {
        $scheme = Utils::asciiToLower($scheme);

        if (!Rfc3986::isValidScheme($scheme)) {
            throw new \InvalidArgumentException(sprintf('Invalid scheme: %s', DiagnosticValue::escape($scheme)));
        }

        return $scheme;
    }

    /**
     * @throws \InvalidArgumentException If the user info is invalid.
     */
    private function filterUserInfoComponent(
        #[\SensitiveParameter]
        string $component
    ): string {
        return $this->filterComponent(
            '/(?:[^%'.Rfc3986::CHAR_UNRESERVED.Rfc3986::CHAR_SUB_DELIMS.']++|%(?!'.Rfc3986::HEX_OCTET.'))/',
            $component,
            'Unable to filter URI user info'
        );
    }

    /**
     * @throws \InvalidArgumentException If the host is invalid.
     */
    private function filterHost(string $host): string
    {
        $host = Utils::asciiToLower($host);
        $filtered = \preg_replace_callback('/%'.Rfc3986::HEX_OCTET.'/', static function (array $m): string {
            return Utils::asciiToUpper($m[0]);
        }, $host);
        if ($filtered === null) {
            throw new \RuntimeException('Unable to normalize URI host percent-encoding: '.\preg_last_error_msg());
        }
        self::assertValidHost($filtered);

        if (str_starts_with($filtered, '[') && !str_starts_with($filtered, '[v')) {
            // assertValidHost() accepted this bracketed value with the same
            // filter_var() predicate tryCanonicalizeIpv6() validates with, and
            // its pure-PHP parse cannot fail on filter-accepted text, so the
            // null guard is defense in depth only.
            $canonical = Rfc3986::tryCanonicalizeIpv6(substr($filtered, 1, -1));
            if ($canonical !== null) {
                $filtered = '['.$canonical.']';
            }
        }

        return $filtered;
    }

    /**
     * @throws \InvalidArgumentException If the port is invalid.
     */
    private function filterPort(?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if (0 > $port || 0xFFFF < $port) {
            throw new \InvalidArgumentException(
                sprintf('Invalid port: %d. Must be between 0 and 65535', $port)
            );
        }

        return $port;
    }

    /**
     * @param mixed $port
     *
     * @throws \InvalidArgumentException If the port is invalid.
     */
    private function filterPortPart($port): ?int
    {
        if (\is_int($port)) {
            return $this->filterPort($port);
        }

        if (\is_string($port) && \ctype_digit($port)) {
            // A zero port is accepted here; only Rfc9112::parsePort() rejects
            // it for HTTP Host/authority parsing.
            if (Rfc3986::isValidPort($port)) {
                return (int) \ltrim($port, '0');
            }

            throw new \InvalidArgumentException(sprintf(
                'Invalid port: %s. Must be between 0 and 65535',
                \ltrim($port, '0')
            ));
        }

        throw new \InvalidArgumentException(sprintf(
            'Invalid port: %s. Must be between 0 and 65535',
            self::describeInvalidPort($port)
        ));
    }

    /**
     * @param mixed $port
     */
    private static function describeInvalidPort($port): string
    {
        if (\is_string($port)) {
            return DiagnosticValue::escape($port);
        }

        if (\is_int($port)) {
            return (string) $port;
        }

        if (\is_bool($port)) {
            return $port ? 'true' : 'false';
        }

        if ($port === null) {
            return 'null';
        }

        if (\is_float($port)) {
            if (\is_nan($port)) {
                return 'NAN';
            }

            if (\is_infinite($port)) {
                return $port > 0 ? 'INF' : '-INF';
            }

            return \sprintf('%.14G', $port);
        }

        return \get_debug_type($port);
    }

    /**
     * @param (string|int)[] $keys
     *
     * @return string[]
     */
    private static function getFilteredQueryString(UriInterface $uri, array $keys): array
    {
        $current = $uri->getQuery();

        if ($current === '') {
            return [];
        }

        $decodedKeys = array_map(function ($k): string {
            return rawurldecode((string) $k);
        }, $keys);

        return array_filter(explode('&', $current), static function (string $part) use ($decodedKeys): bool {
            return !in_array(rawurldecode(explode('=', $part)[0]), $decodedKeys, true);
        });
    }

    private static function generateQueryString(string $key, ?string $value): string
    {
        // Query string separators ("=", "&") and literal plus signs ("+") within the
        // key or value need to be encoded
        // (while preventing double-encoding) before setting the query string. All other
        // chars that need percent-encoding will be encoded by withQuery().
        $queryString = strtr($key, self::QUERY_SEPARATORS_REPLACEMENT);

        if ($value !== null) {
            $queryString .= '='.strtr($value, self::QUERY_SEPARATORS_REPLACEMENT);
        }

        return $queryString;
    }

    private function removeDefaultPort(): void
    {
        if ($this->port !== null && self::isDefaultPort($this)) {
            $this->port = null;
        }
    }

    /**
     * Filters the path of a URI
     *
     * @throws \InvalidArgumentException If the path is invalid.
     */
    private function filterPath(string $path): string
    {
        return $this->filterComponent(
            '/(?:[^'.Rfc3986::CHAR_UNRESERVED.Rfc3986::CHAR_SUB_DELIMS.'%:@\/]++|%(?!'.Rfc3986::HEX_OCTET.'))/',
            $path,
            'Unable to filter URI path'
        );
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @throws \InvalidArgumentException If the query or fragment is invalid.
     */
    private function filterQueryAndFragment(string $str): string
    {
        return $this->filterComponent(
            '/(?:[^'.Rfc3986::CHAR_UNRESERVED.Rfc3986::CHAR_SUB_DELIMS.'%:@\/\?]++|%(?!'.Rfc3986::HEX_OCTET.'))/',
            $str,
            'Unable to filter URI query or fragment'
        );
    }

    private function filterComponent(
        string $pattern,
        #[\SensitiveParameter]
        string $component,
        string $context
    ): string {
        $filtered = preg_replace_callback($pattern, [$this, 'rawurlencodeMatchZero'], $component);

        if ($filtered === null) {
            throw new \RuntimeException($context.': '.preg_last_error_msg());
        }

        return $filtered;
    }

    private function rawurlencodeMatchZero(array $match): string
    {
        return rawurlencode($match[0]);
    }

    private function validateState(): void
    {
        if ($this->host === '' && ($this->scheme === 'http' || $this->scheme === 'https')) {
            $this->host = self::HTTP_DEFAULT_HOST;
        }

        if ($this->getAuthority() === '') {
            if (str_starts_with($this->path, '//')) {
                throw new MalformedUriException('The path of a URI without an authority must not start with two slashes "//"');
            }
            if ($this->scheme === '' && str_contains(explode('/', $this->path, 2)[0], ':')) {
                throw new MalformedUriException('A relative URI must not have a path beginning with a segment containing a colon');
            }
        }
    }
}
