<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 request implementation.
 */
class Request implements RequestInterface
{
    use MessageTrait;

    private string $method;

    private ?string $requestTarget = null;

    private UriInterface $uri;

    /**
     * @param string                               $method  HTTP method
     * @param string|UriInterface                  $uri     URI
     * @param (string|string[])[]                  $headers Request headers
     * @param string|resource|StreamInterface|null $body    Request body
     * @param string                               $version Protocol version
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1'
    ) {
        $this->assertMethod($method);
        $this->assertProtocolVersion($version);

        if (!$uri instanceof UriInterface) {
            $uri = new Uri($uri);
        }
        self::getRequestTargetFromUri($uri);

        $this->method = $method;
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocol = $version;

        if (!isset($this->headerNames['host'])) {
            $this->updateHostFromUri();
        }

        if ($body !== '' && $body !== null) {
            $this->stream = Utils::streamFor($body);
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        return self::getRequestTargetFromUri($this->uri);
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        self::assertRequestTarget($requestTarget);

        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        $this->assertMethod($method);
        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $sameUri = $uri === $this->uri;

        if (!$sameUri && $this->requestTarget === null) {
            self::getRequestTargetFromUri($uri);
        }

        $currentHost = $this->getHeaderLine('Host');
        $host = null;

        if (!$preserveHost || $currentHost === '') {
            $host = $this->getHostFromUri($uri);
        }

        if ($sameUri && ($host === null || $currentHost === $host)) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if ($host !== null) {
            $new->setHostHeader($host);
        }

        return $new;
    }

    private function updateHostFromUri(): void
    {
        $host = $this->getHostFromUri($this->uri);

        if ($host === null) {
            return;
        }

        $this->setHostHeader($host);
    }

    private function getHostFromUri(UriInterface $uri): ?string
    {
        $host = $uri->getHost();

        if ($host === '') {
            return null;
        }

        Uri::assertValidHost($host);

        if (($port = $uri->getPort()) !== null) {
            $host .= ':'.$port;
        }

        $this->assertValue('Host', $host);

        return $host;
    }

    private function setHostHeader(string $host): void
    {
        if (isset($this->headerNames['host'])) {
            $header = $this->headerNames['host'];
        } else {
            $header = 'Host';
            $this->headerNames['host'] = 'Host';
        }
        // Ensure Host is the first header.
        // See: https://datatracker.ietf.org/doc/html/rfc9110#section-7.2
        $this->headers = [$header => [$host]] + $this->headers;
    }

    private function assertMethod(string $method): void
    {
        if (!Rfc9110::isToken($method)) {
            throw new InvalidArgumentException('Method must be a valid HTTP token.');
        }
    }

    private static function getRequestTargetFromUri(UriInterface $uri): string
    {
        $target = self::normalizePathForOriginForm($uri->getPath());
        if ($target === '') {
            $target = '/';
        }
        if ($uri->getQuery() != '') {
            $target .= '?'.$uri->getQuery();
        }

        self::assertRequestTarget($target);

        return $target;
    }

    private static function assertRequestTarget(string $requestTarget): void
    {
        if (!Rfc9112::isValidRequestTarget($requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target provided; cannot be empty or contain whitespace or control characters'
            );
        }
    }

    private static function normalizePathForOriginForm(string $path): string
    {
        if (str_starts_with($path, '//')) {
            return '/'.ltrim($path, '/');
        }

        return $path;
    }
}
