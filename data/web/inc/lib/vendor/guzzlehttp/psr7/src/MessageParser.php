<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class MessageParser
{
    private function __construct()
    {
    }

    public static function parseMessage(string $message): array
    {
        if (!$message) {
            throw new \InvalidArgumentException('Invalid message');
        }

        $message = ltrim($message, "\r\n");

        $messageParts = preg_split("/\r?\n\r?\n/", $message, 2);

        if ($messageParts === false) {
            throw new \RuntimeException('Unable to split HTTP message: '.preg_last_error_msg());
        }

        if (count($messageParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing header delimiter');
        }

        [$rawHeaders, $body] = $messageParts;
        $rawHeaders .= "\r\n"; // Put back the delimiter we split previously
        $headerParts = preg_split("/\r?\n/", $rawHeaders, 2);

        if ($headerParts === false) {
            throw new \RuntimeException('Unable to split HTTP message headers: '.preg_last_error_msg());
        }

        if (count($headerParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing status line');
        }

        [$startLine, $rawHeaders] = $headerParts;

        $versionMatch = preg_match(
            '/(?:^HTTP\/|^'.Rfc9110::TOKEN_PATTERN.' '.Rfc9112::REQUEST_TARGET_PATTERN.' HTTP\/)('.Rfc9112::PROTOCOL_VERSION_PATTERN.')/i',
            $startLine,
            $matches
        );

        if ($versionMatch === false) {
            throw new \RuntimeException('Unable to parse HTTP start line: '.preg_last_error_msg());
        }

        if ($versionMatch === 1 && $matches[1] === '1.0') {
            // Header folding is deprecated for HTTP/1.1, but allowed in HTTP/1.0
            $rawHeaders = preg_replace(Rfc9112::HEADER_FOLD_REGEX, ' ', $rawHeaders);

            if ($rawHeaders === null) {
                throw new \RuntimeException('Unable to unfold HTTP headers: '.preg_last_error_msg());
            }
        }

        $count = preg_match_all(Rfc9112::HEADER_REGEX, $rawHeaders, $headerLines, PREG_SET_ORDER);
        /** @var list<array<int, string>> $headerLines */
        if ($count === false) {
            throw new \RuntimeException('Unable to parse HTTP headers: '.preg_last_error_msg());
        }

        // If these aren't the same, then one line didn't match and there's an invalid header.
        if ($count !== substr_count($rawHeaders, "\n")) {
            // Folding is deprecated, see https://datatracker.ietf.org/doc/html/rfc9112#section-5.2
            $hasFoldedHeader = preg_match(Rfc9112::HEADER_FOLD_REGEX, $rawHeaders);

            if ($hasFoldedHeader === false) {
                throw new \RuntimeException('Unable to inspect HTTP header folding: '.preg_last_error_msg());
            }

            if ($hasFoldedHeader === 1) {
                throw new \InvalidArgumentException('Invalid header syntax: Obsolete line folding');
            }

            throw new \InvalidArgumentException('Invalid header syntax');
        }

        $headers = [];

        foreach ($headerLines as $headerLine) {
            $headers[$headerLine[1]][] = $headerLine[2];
        }

        return [
            'start-line' => $startLine,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    public static function parseRequestUri(string $path, array $headers): string
    {
        $host = self::getHostFromHeaders($headers);

        // If no host is found, then a full URI cannot be constructed.
        // Collapse leading slashes so an origin-form target cannot be
        // parsed as a network-path reference with its own authority.
        if ($host === null) {
            return self::normalizePathForOriginForm($path);
        }

        [$authorityHost, $port] = self::parseHostHeaderAuthority($host);
        $scheme = $port === 443 ? 'https' : 'http';

        return $scheme.'://'.self::composeAuthority($authorityHost, $port).'/'.ltrim($path, '/');
    }

    private static function normalizePathForOriginForm(string $path): string
    {
        if (str_starts_with($path, '//')) {
            return '/'.ltrim($path, '/');
        }

        return $path;
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private static function parseHostHeaderAuthority(string $authority): array
    {
        $parsed = Rfc9112::parseHostHeader($authority);
        if ($parsed === null) {
            throw new \InvalidArgumentException('Invalid request string');
        }

        return $parsed;
    }

    private static function composeAuthority(string $host, ?int $port): string
    {
        return $host.($port !== null ? ':'.$port : '');
    }

    /**
     * @param array $headers Array of headers (each value an array).
     */
    private static function getHostFromHeaders(array $headers): ?string
    {
        $host = self::getSingleHostHeader($headers);
        if ($host === null) {
            return null;
        }

        self::parseHostHeaderAuthority($host);

        return $host;
    }

    /**
     * @param array $headers Array of headers (each value an array).
     */
    private static function getSingleHostHeader(array $headers): ?string
    {
        $host = null;
        $found = false;

        foreach ($headers as $name => $values) {
            if (Utils::asciiToLower((string) $name) !== 'host') {
                continue;
            }

            if ($found || !is_array($values) || count($values) !== 1) {
                throw new \InvalidArgumentException('Invalid request string');
            }

            $found = true;
            $host = reset($values);
        }

        if (!$found) {
            return null;
        }

        if (!is_string($host)) {
            throw new \InvalidArgumentException('Invalid request string');
        }

        return $host;
    }

    /**
     * @param array $headers Array of headers (each value an array).
     */
    private static function parseRequestAuthorityUri(array $headers): string
    {
        $host = self::getHostFromHeaders($headers);
        if ($host === null) {
            return '';
        }

        [$authorityHost, $port] = self::parseHostHeaderAuthority($host);
        $scheme = $port === 443 ? 'https' : 'http';

        return $scheme.'://'.self::composeAuthority($authorityHost, $port);
    }

    public static function parseRequest(string $message): RequestInterface
    {
        $data = self::parseMessage($message);
        $matches = [];
        $matched = preg_match(
            '/^(?P<method>'.Rfc9110::TOKEN_PATTERN.') (?P<target>'.Rfc9112::REQUEST_TARGET_PATTERN.') HTTP\/(?P<version>'.Rfc9112::PROTOCOL_VERSION_PATTERN.')$/D',
            $data['start-line'],
            $matches
        );

        if ($matched === false) {
            throw new \RuntimeException('Unable to parse request start line: '.preg_last_error_msg());
        }

        if ($matched === 0) {
            throw new \InvalidArgumentException('Invalid request string');
        }

        self::getHostFromHeaders($data['headers']);

        if (str_starts_with($matches['target'], '/')) {
            return new Request(
                $matches['method'],
                self::parseRequestUri($matches['target'], $data['headers']),
                $data['headers'],
                $data['body'],
                $matches['version']
            );
        }

        $absoluteFormUri = self::parseAbsoluteFormRequestTarget($matches['target']);
        if ($absoluteFormUri !== null) {
            return (new Request(
                $matches['method'],
                $absoluteFormUri,
                $data['headers'],
                $data['body'],
                $matches['version']
            ))->withRequestTarget($matches['target']);
        }

        if (Rfc9112::isAsteriskFormRequestTarget($matches['method'], $matches['target'])) {
            return (new Request(
                $matches['method'],
                self::parseRequestAuthorityUri($data['headers']),
                $data['headers'],
                $data['body'],
                $matches['version']
            ))->withRequestTarget($matches['target']);
        }

        $connectUri = self::parseConnectAuthorityFormRequestTarget($matches['method'], $matches['target']);
        if ($connectUri !== null) {
            return (new Request(
                $matches['method'],
                $connectUri,
                $data['headers'],
                $data['body'],
                $matches['version']
            ))->withRequestTarget($matches['target']);
        }

        throw new \InvalidArgumentException('Invalid request string');
    }

    private static function parseAbsoluteFormRequestTarget(string $target): ?Uri
    {
        if (!Rfc9112::isAbsoluteFormRequestTarget($target)) {
            return null;
        }

        $authority = substr($target, strpos($target, '//') + 2);
        $authority = substr($authority, 0, strcspn($authority, '/?#'));

        // RFC 9110 deprecates userinfo in message target URIs and directs
        // recipients to treat its presence as an error, since it can obscure
        // the authority. Host headers and CONNECT targets already reject it.
        if (str_contains($authority, '@')) {
            return null;
        }

        try {
            $uri = new Uri($target);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        if ($uri->getHost() === '') {
            return null;
        }

        try {
            self::parseHostHeaderAuthority(self::composeAuthority($uri->getHost(), $uri->getPort()));
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return $uri;
    }

    private static function parseConnectAuthorityFormRequestTarget(string $method, string $target): ?Uri
    {
        if (!Rfc9112::isConnectAuthorityFormRequestTarget($method, $target)) {
            return null;
        }

        $parsed = Rfc9112::parseHostHeader($target);
        if ($parsed === null) {
            return null;
        }

        [$host, $port] = $parsed;
        if ($port === null) {
            return null;
        }

        try {
            return new Uri('//'.self::composeAuthority($host, $port));
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    public static function parseResponse(string $message): ResponseInterface
    {
        $data = self::parseMessage($message);
        // According to https://datatracker.ietf.org/doc/html/rfc9112#section-4
        // the space between status-code and reason-phrase is required. But
        // browsers accept responses without space and reason as well.
        $matched = preg_match(
            '/^HTTP\/(?P<version>'.Rfc9112::PROTOCOL_VERSION_PATTERN.') (?P<status>[1-5][0-9]{2})(?: (?P<reason>'.Rfc9110::FIELD_VALUE_PATTERN.'))?$/D',
            $data['start-line'],
            $matches
        );

        if ($matched === false) {
            throw new \RuntimeException('Unable to parse response start line: '.preg_last_error_msg());
        }

        if ($matched === 0) {
            throw new \InvalidArgumentException(\sprintf('Invalid response string: %s', DiagnosticValue::escape($data['start-line'])));
        }

        return new Response(
            (int) $matches['status'],
            $data['headers'],
            $data['body'],
            $matches['version'],
            $matches['reason'] ?? null
        );
    }
}
