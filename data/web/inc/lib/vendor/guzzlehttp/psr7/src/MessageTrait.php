<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Trait implementing functionality common to requests and responses.
 */
trait MessageTrait
{
    /** @var string[][] Map of all registered headers, as original name => array of values */
    private array $headers = [];

    /** @var string[] Map of lowercase header name => original name at registration */
    private array $headerNames = [];

    private string $protocol = '1.1';

    private ?StreamInterface $stream = null;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * @return static
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        $this->assertProtocolVersion($version);

        if ($this->protocol === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = $version;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[Utils::asciiToLower($name)]);
    }

    public function getHeader(string $name): array
    {
        $header = Utils::asciiToLower($name);

        if (!isset($this->headerNames[$header])) {
            return [];
        }

        $header = $this->headerNames[$header];

        return $this->headers[$header];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @return static
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $this->assertHeader($name);
        $value = $this->normalizeHeaderValue($name, $value);
        $normalized = Utils::asciiToLower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * @return static
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $this->assertHeader($name);
        $value = $this->normalizeHeaderValue($name, $value);
        $normalized = Utils::asciiToLower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            $name = $this->headerNames[$normalized];
            $new->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    /**
     * @return static
     */
    public function withoutHeader(string $name): MessageInterface
    {
        $normalized = Utils::asciiToLower($name);

        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $name = $this->headerNames[$normalized];

        $new = clone $this;
        unset($new->headers[$name], $new->headerNames[$normalized]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        if (!$this->stream) {
            $this->stream = Utils::streamFor('');
        }

        return $this->stream;
    }

    /**
     * @return static
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    /**
     * @param (string|string[])[] $headers
     */
    private function setHeaders(array $headers): void
    {
        $this->headerNames = $this->headers = [];
        foreach ($headers as $header => $value) {
            // Numeric array keys are converted to int by PHP.
            $header = (string) $header;

            $this->assertHeader($header);
            $value = $this->normalizeHeaderValue($header, $value);
            $normalized = Utils::asciiToLower($header);
            if (isset($this->headerNames[$normalized])) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private function normalizeHeaderValue(string $header, $value): array
    {
        if (is_array($value) && $value === []) {
            throw new \InvalidArgumentException('Header value must be a non-empty array or string.');
        }

        if (!is_array($value)) {
            return $this->trimAndValidateHeaderValues($header, [$value]);
        }

        return $this->trimAndValidateHeaderValues($header, $value);
    }

    /**
     * Trims whitespace from the header values.
     *
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     *
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB )
     *
     * @param mixed[] $values Header values
     *
     * @return string[] Trimmed header values
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9110#section-5.5
     */
    private function trimAndValidateHeaderValues(string $header, array $values): array
    {
        return array_map(function ($value) use ($header): string {
            if (!is_string($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'Header value must be a string or array of strings but %s provided.',
                    \get_debug_type($value)
                ));
            }

            $trimmed = trim($value, " \t");
            $this->assertValue($header, $trimmed);

            return $trimmed;
        }, array_values($values));
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc9110#section-5.1
     */
    private function assertHeader(string $header): void
    {
        if (!Rfc9110::isToken($header)) {
            throw new \InvalidArgumentException(sprintf('Invalid header name: %s', DiagnosticValue::escape($header)));
        }
    }

    private function assertProtocolVersion(string $version): void
    {
        if (!Rfc9112::isValidProtocolVersion($version)) {
            throw new \InvalidArgumentException('Protocol version must be a valid HTTP version number.');
        }
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc9110#section-5.5
     *
     * field-value    = *( field-content / obs-fold )
     * field-content  = field-vchar [ 1*( SP / HTAB ) field-vchar ]
     * field-vchar    = VCHAR / obs-text
     * VCHAR          = %x21-7E
     * obs-text       = %x80-FF
     * obs-fold       = CRLF 1*( SP / HTAB )
     */
    private function assertValue(string $header, string $value): void
    {
        // The regular expression intentionally does not support the obs-fold
        // production, because as per RFC 9112#5.2:
        //
        // A sender MUST NOT generate a message that includes line folding
        // (i.e., that has any field-value that contains a match to the obs-fold
        // rule) unless the message is intended for packaging within the
        // message/http media type.
        //
        // Clients must not send a request with line folding and a server
        // sending folded headers is likely very rare. Line folding is a fairly
        // obscure feature of HTTP/1.1 and thus not accepting folding is not
        // likely to break any legitimate use case.
        if (!Rfc9110::isFieldValue($value)) {
            $reason = strpbrk($value, "\r\n") !== false
                ? 'must not contain CR or LF characters'
                : 'contains an invalid control character';

            throw new \InvalidArgumentException(sprintf('Header "%s" %s.', DiagnosticValue::escape($header), $reason));
        }
    }
}
