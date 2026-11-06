<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class ServerRequestGlobalsFactory
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed>  $server         Typically the $_SERVER superglobal
     * @param array<array-key, mixed>  $query          Typically the $_GET superglobal
     * @param array<array-key, mixed>  $post           Typically the $_POST superglobal
     * @param array<array-key, mixed>  $cookies        Typically the $_COOKIE superglobal
     * @param array<array-key, mixed>  $files          Typically the $_FILES superglobal
     * @param (callable(): mixed)|null $headerProvider
     */
    public static function fromArrays(
        #[\SensitiveParameter]
        array $server,
        array $query,
        array $post,
        #[\SensitiveParameter]
        array $cookies,
        array $files,
        ?callable $headerProvider = null
    ): ServerRequestInterface {
        $method = self::getRequestMethodFromServer($server);
        $headers = self::removeInvalidHostHeader(self::getAllHeaders($server, $headerProvider));
        [$uri, $requestTarget] = self::getUriAndRequestTargetFromServer($server, $method);
        $body = new CachingStream(new LazyOpenStream('php://input', 'r+'));

        $serverRequest = new ServerRequest($method, $uri, $headers, $body, self::getProtocolFromServer($server), $server);
        if ($requestTarget !== null) {
            /** @var ServerRequestInterface $serverRequest */
            $serverRequest = $serverRequest->withRequestTarget($requestTarget);
        }

        return $serverRequest
            ->withCookieParams($cookies)
            ->withQueryParams($query)
            ->withParsedBody($post)
            ->withUploadedFiles(UploadedFileNormalizer::normalize($files));
    }

    /**
     * @param array<array-key, mixed> $server Typically the $_SERVER superglobal
     */
    public static function getUriFromServerParams(
        #[\SensitiveParameter]
        array $server
    ): UriInterface {
        $method = self::getRequestMethodFromServer($server);

        return self::getUriAndRequestTargetFromServer($server, $method)[0];
    }

    /**
     * @param array<array-key, mixed>  $server
     * @param (callable(): mixed)|null $headerProvider
     *
     * @return array<array-key, string>
     */
    private static function getAllHeaders(
        #[\SensitiveParameter]
        array $server,
        ?callable $headerProvider
    ): array {
        $headers = $headerProvider !== null ? $headerProvider() : false;

        if (!is_array($headers)) {
            $headers = self::getHeadersFromServer($server);
        }

        return self::normalizeHeaderValues($headers);
    }

    /**
     * @param array<array-key, mixed> $headers
     *
     * @return array<array-key, string>
     */
    private static function normalizeHeaderValues(
        #[\SensitiveParameter]
        array $headers
    ): array {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $normalized[$name] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $server Typically the $_SERVER superglobal
     *
     * @return array<array-key, string>
     */
    private static function getHeadersFromServer(array $server): array
    {
        $headers = [];

        $copyServer = [
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5' => 'Content-Md5',
        ];

        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $header = substr($key, 5);

                if (isset($copyServer[$header], $server[$header]) && is_string($server[$header])) {
                    continue;
                }

                $parts = explode(' ', Utils::asciiToLower(str_replace('_', ' ', $header)));
                foreach ($parts as $i => $part) {
                    $parts[$i] = Utils::asciiUcFirst($part);
                }
                $header = implode('-', $parts);
                $headers[$header] = $value;

                continue;
            }

            if (isset($copyServer[$key])) {
                $headers[$copyServer[$key]] = $value;
            }
        }

        if (!isset($headers['Authorization'])) {
            if (isset($server['REDIRECT_HTTP_AUTHORIZATION']) && is_string($server['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($server['PHP_AUTH_USER']) && is_string($server['PHP_AUTH_USER'])) {
                $password = isset($server['PHP_AUTH_PW']) && is_string($server['PHP_AUTH_PW'])
                    ? $server['PHP_AUTH_PW']
                    : '';

                $headers['Authorization'] = 'Basic '.base64_encode($server['PHP_AUTH_USER'].':'.$password);
            } elseif (isset($server['PHP_AUTH_DIGEST']) && is_string($server['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $server['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }

    /**
     * @param array<array-key, string> $headers
     *
     * @return array<array-key, string>
     */
    private static function removeInvalidHostHeader(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (Utils::asciiToLower((string) $name) !== 'host') {
                continue;
            }

            [$host] = self::extractHostAndPortFromAuthority($value);
            if ($host === null) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function getServerParam(array $server, string $key): ?string
    {
        return isset($server[$key]) && is_string($server[$key]) ? $server[$key] : null;
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function getRequestMethodFromServer(array $server): string
    {
        return Utils::asciiToUpper(self::getServerParam($server, 'REQUEST_METHOD') ?? 'GET');
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function getProtocolFromServer(array $server): string
    {
        $serverProtocol = self::getServerParam($server, 'SERVER_PROTOCOL');
        if ($serverProtocol === null) {
            return '1.1';
        }

        return str_starts_with($serverProtocol, 'HTTP/') ? substr($serverProtocol, 5) : $serverProtocol;
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private static function extractHostAndPortFromAuthority(string $authority): array
    {
        return Rfc9112::parseHostHeader($authority) ?? [null, null];
    }

    private static function parseServerPort(string $port): int
    {
        $parsed = Rfc9112::parsePort($port);
        if ($parsed === null) {
            throw new InvalidArgumentException('Invalid SERVER_PORT; expected an integer between 1 and 65535.');
        }

        return $parsed;
    }

    private static function withHostFromServer(UriInterface $uri, ?string $host): ?UriInterface
    {
        if ($host === null) {
            return null;
        }

        try {
            return $uri->withHost($host);
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function getUriWithSchemeFromServer(array $server): UriInterface
    {
        $uri = new Uri('');

        $https = self::getServerParam($server, 'HTTPS');

        return $uri->withScheme(!empty($https) && $https !== 'off' ? 'https' : 'http');
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function getAuthorityUriFromServer(
        #[\SensitiveParameter]
        array $server
    ): UriInterface {
        $uri = self::getUriWithSchemeFromServer($server);

        $hasPort = false;
        $hasHost = false;
        $authority = self::getServerParam($server, 'HTTP_HOST');
        if ($authority !== null) {
            [$host, $port] = self::extractHostAndPortFromAuthority($authority);
            if ($host !== null) {
                $hostUri = self::withHostFromServer($uri, $host);
                if ($hostUri !== null) {
                    $uri = $hostUri;
                    $hasHost = true;

                    if ($port !== null) {
                        $hasPort = true;
                        $uri = $uri->withPort($port);
                    }
                }
            }
        }

        foreach (['SERVER_NAME', 'SERVER_ADDR'] as $serverParam) {
            if ($hasHost) {
                continue;
            }

            $hostUri = self::withHostFromServer($uri, self::getServerParam($server, $serverParam));
            if ($hostUri !== null) {
                $uri = $hostUri;
                $hasHost = true;
            }
        }

        $serverPort = self::getServerParam($server, 'SERVER_PORT');
        if (!$hasPort && $serverPort !== null) {
            $uri = $uri->withPort(self::parseServerPort($serverPort));
        }

        return $uri;
    }

    /**
     * @param array<array-key, mixed> $server
     *
     * @return array{0: UriInterface, 1: string|null}
     */
    private static function getUriAndRequestTargetFromServer(
        #[\SensitiveParameter]
        array $server,
        string $method
    ): array {
        $requestUri = self::getServerParam($server, 'REQUEST_URI');
        $queryString = self::getServerParam($server, 'QUERY_STRING');

        if ($requestUri !== null) {
            $connectAuthority = self::parseConnectAuthorityFormRequestTarget($method, $requestUri);
            if ($connectAuthority !== null) {
                [$host, $port] = $connectAuthority;
                $uri = self::getUriWithSchemeFromServer($server);

                return [
                    $uri->withHost($host)->withPort($port)->withPath('')->withQuery(''),
                    $requestUri,
                ];
            }

            $absoluteForm = self::getAbsoluteFormUriAndRequestTarget($requestUri, $queryString);
            if ($absoluteForm !== null) {
                return $absoluteForm;
            }
        }

        $uri = self::getAuthorityUriFromServer($server);

        if ($requestUri === null) {
            if ($queryString !== null) {
                $uri = $uri->withQuery($queryString);
            }

            return [$uri, null];
        }

        if (Rfc9112::isAsteriskFormRequestTarget($method, $requestUri)) {
            return [$uri->withPath('')->withQuery(''), '*'];
        }

        [$path, $query, $hasQuery] = self::splitRequestTargetQuery($requestUri);
        $uri = $uri->withPath(self::normalizeOriginFormPathFromServer($path));

        if ($hasQuery) {
            $uri = $uri->withQuery($query);
        } elseif ($queryString !== null) {
            $uri = $uri->withQuery($queryString);
        }

        return [$uri, null];
    }

    /**
     * @return array{0: UriInterface, 1: string}|null
     */
    private static function getAbsoluteFormUriAndRequestTarget(
        #[\SensitiveParameter]
        string $requestUri,
        ?string $queryString
    ): ?array {
        if (!Rfc9112::isAbsoluteFormRequestTarget($requestUri)) {
            return null;
        }

        try {
            $targetUri = (new Uri($requestUri))->withFragment('');
        } catch (InvalidArgumentException $e) {
            return null;
        }

        if ($targetUri->getHost() === '') {
            return null;
        }

        $requestTarget = self::removeRequestTargetFragment($requestUri);
        $requestTargetWithoutUserInfo = self::removeUserInfoFromAbsoluteFormRequestTarget($requestTarget);
        if ($requestTargetWithoutUserInfo !== $requestTarget) {
            $targetUri = $targetUri->withUserInfo('');
            $requestTarget = $requestTargetWithoutUserInfo;
        }

        if (!str_contains($requestTarget, '?') && $queryString !== null && $queryString !== '') {
            $targetUri = $targetUri->withQuery($queryString);
            $requestTarget .= '?'.$queryString;
        }

        // Preserve the received absolute-form target unless it cannot be used as
        // a PSR-7 request target without normalization.
        $normalizeRequestTarget = !Rfc9112::isValidRequestTarget($requestTarget)
            || self::hasEmptyPortInAbsoluteFormRequestTarget($requestTarget);

        return [$targetUri, $normalizeRequestTarget ? (string) $targetUri : $requestTarget];
    }

    private static function removeUserInfoFromAbsoluteFormRequestTarget(string $target): string
    {
        $authorityStart = strpos($target, '://');
        if ($authorityStart === false) {
            return $target;
        }

        $authorityStart += 3;
        $authorityLength = strcspn($target, '/?#', $authorityStart);
        $authority = substr($target, $authorityStart, $authorityLength);
        if ($authority === '') {
            return $target;
        }

        $lastAt = strrpos($authority, '@');
        if ($lastAt === false) {
            return $target;
        }

        $authorityEnd = $authorityStart + $authorityLength;

        return substr($target, 0, $authorityStart)
            .substr($authority, $lastAt + 1)
            .substr($target, $authorityEnd);
    }

    private static function hasEmptyPortInAbsoluteFormRequestTarget(string $target): bool
    {
        $authorityStart = strpos($target, '://');
        if ($authorityStart === false) {
            return false;
        }

        $authorityStart += 3;
        $authority = substr($target, $authorityStart, strcspn($target, '/?#', $authorityStart));
        if ($authority === '') {
            return false;
        }

        $lastAt = strrpos($authority, '@');
        if ($lastAt !== false) {
            $authority = substr($authority, $lastAt + 1);
        }

        if ($authority === '') {
            return false;
        }

        if (str_starts_with($authority, '[')) {
            $closingBracket = strpos($authority, ']');

            return $closingBracket !== false && substr($authority, $closingBracket + 1) === ':';
        }

        return str_ends_with($authority, ':');
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private static function parseConnectAuthorityFormRequestTarget(string $method, string $target): ?array
    {
        if (!Rfc9112::isConnectAuthorityFormRequestTarget($method, $target)) {
            return null;
        }

        [$host, $port] = self::extractHostAndPortFromAuthority($target);
        if ($host === null || $port === null) {
            return null;
        }

        return [$host, $port];
    }

    private static function removeRequestTargetFragment(string $target): string
    {
        return explode('#', $target, 2)[0];
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private static function splitRequestTargetQuery(string $target): array
    {
        $parts = explode('?', $target, 2);

        return [$parts[0], $parts[1] ?? '', isset($parts[1])];
    }

    private static function normalizeOriginFormPathFromServer(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        return '/'.$path;
    }
}
