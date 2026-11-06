<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    private array $attributes = [];

    private array $cookieParams = [];

    /**
     * @var array|object|null
     */
    private $parsedBody;

    private array $queryParams = [];

    private array $serverParams;

    private array $uploadedFiles = [];

    /**
     * @param string                               $method       HTTP method
     * @param string|UriInterface                  $uri          URI
     * @param (string|string[])[]                  $headers      Request headers
     * @param string|resource|StreamInterface|null $body         Request body
     * @param string                               $version      Protocol version
     * @param array                                $serverParams Typically the $_SERVER superglobal
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        #[\SensitiveParameter]
        array $serverParams = []
    ) {
        $this->serverParams = $serverParams;

        parent::__construct($method, $uri, $headers, $body, $version);
    }

    /**
     * Return an UploadedFile instance array.
     *
     * @param array $files An array which respect $_FILES structure
     *
     * @throws InvalidArgumentException for unrecognized values
     */
    public static function normalizeFiles(array $files): array
    {
        return UploadedFileNormalizer::normalize($files);
    }

    /**
     * Return a ServerRequest populated with superglobals:
     * $_GET
     * $_POST
     * $_COOKIE
     * $_FILES
     * $_SERVER
     */
    public static function fromGlobals(): ServerRequestInterface
    {
        return ServerRequestGlobalsFactory::fromArrays(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            static function () {
                if (!\function_exists('apache_request_headers')) {
                    return false;
                }

                return \apache_request_headers();
            }
        );
    }

    /**
     * Get a Uri populated with values from $_SERVER.
     */
    public static function getUriFromGlobals(): UriInterface
    {
        return ServerRequestGlobalsFactory::getUriFromServerParams($_SERVER);
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $stack = [$uploadedFiles];

        for ($i = 0; $i < \count($stack); ++$i) {
            foreach ($stack[$i] as $uploadedFile) {
                if ($uploadedFile instanceof UploadedFileInterface) {
                    continue;
                }

                if (\is_array($uploadedFile)) {
                    $stack[] = $uploadedFile;
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Invalid uploaded file tree; expected UploadedFileInterface instances but %s provided.',
                    \get_debug_type($uploadedFile)
                ));
            }
        }

        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(
        #[\SensitiveParameter]
        array $cookies
    ): ServerRequestInterface {
        $new = clone $this;
        $new->cookieParams = $cookies;

        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    /**
     * @return array|object|null
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        if ($data !== null && !\is_array($data) && !\is_object($data)) {
            throw new InvalidArgumentException('Parsed body must be an array, object, or null.');
        }

        $new = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$name];
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);

        return $new;
    }
}
