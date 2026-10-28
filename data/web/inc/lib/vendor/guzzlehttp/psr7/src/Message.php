<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

final class Message
{
    private const DEFAULT_BODY_SUMMARY_TRUNCATE_AT = 120;

    private function __construct()
    {
    }

    /**
     * Returns the string representation of an HTTP message.
     *
     * @param MessageInterface $message Message to convert to a string.
     */
    public static function toString(MessageInterface $message): string
    {
        if ($message instanceof RequestInterface) {
            $msg = trim($message->getMethod().' '
                    .$message->getRequestTarget(), " \n\r\t\0\x0B")
                .' HTTP/'.$message->getProtocolVersion();
            if (!$message->hasHeader('host')) {
                $msg .= "\r\nHost: ".self::hostHeaderFromUri($message->getUri());
            }
        } elseif ($message instanceof ResponseInterface) {
            $msg = 'HTTP/'.$message->getProtocolVersion().' '
                .$message->getStatusCode().' '
                .$message->getReasonPhrase();
        } else {
            throw new \InvalidArgumentException('Unknown message type');
        }

        foreach ($message->getHeaders() as $name => $values) {
            if (is_string($name) && Utils::asciiToLower($name) === 'set-cookie') {
                foreach ($values as $value) {
                    $msg .= "\r\n{$name}: ".$value;
                }
            } else {
                $msg .= "\r\n{$name}: ".implode(', ', $values);
            }
        }

        return "{$msg}\r\n\r\n".$message->getBody();
    }

    private static function hostHeaderFromUri(UriInterface $uri): string
    {
        $host = $uri->getHost();

        if ($host === '') {
            return '';
        }

        Uri::assertValidHost($host);

        if (($port = $uri->getPort()) !== null) {
            $host .= ':'.$port;
        }

        return $host;
    }

    /**
     * Get a short summary of the message body.
     *
     * Will return `null` if the response is not printable.
     *
     * Reads seekable bodies from the beginning and restores the original cursor
     * position before returning. Pass `null` for `$truncateAt` to use the
     * default summary length.
     *
     * @param MessageInterface $message    The message to get the body summary
     * @param int|null         $truncateAt Maximum allowed size of the summary
     */
    public static function bodySummary(MessageInterface $message, ?int $truncateAt = null): ?string
    {
        $truncateAt ??= self::DEFAULT_BODY_SUMMARY_TRUNCATE_AT;

        $body = $message->getBody();

        if (!$body->isSeekable() || !$body->isReadable()) {
            return null;
        }

        $size = $body->getSize();

        if ($size === 0) {
            return null;
        }

        $position = $body->tell();

        try {
            $body->rewind();
            $summary = $body->read($truncateAt);

            if ($size > $truncateAt) {
                if (preg_match('//u', $summary) !== 1) {
                    $summary = self::trimTrailingIncompleteUtf8Character($summary, $body->read(3));
                }

                $summary .= ' (truncated...)';
            }
        } finally {
            $body->seek($position);
        }

        // Matches any printable character, including unicode characters:
        // letters, marks, numbers, punctuation, spacing, and separators.
        if (preg_match('/[^\pL\pM\pN\pP\pS\pZ\n\r\t]/u', $summary) !== 0) {
            return null;
        }

        return $summary;
    }

    /**
     * Trims a partial UTF-8 character from the end of a truncated string.
     */
    private static function trimTrailingIncompleteUtf8Character(string $summary, string $lookahead): string
    {
        $length = strlen($summary);

        if ($length === 0) {
            return $summary;
        }

        $start = $length - 1;

        while ($start >= 0) {
            $byte = ord($summary[$start]);

            if ($byte < 0x80 || $byte > 0xBF) {
                break;
            }

            --$start;
        }

        if ($start < 0) {
            return $summary;
        }

        $lead = ord($summary[$start]);

        if ($lead >= 0xC2 && $lead <= 0xDF) {
            $expectedLength = 2;
        } elseif ($lead >= 0xE0 && $lead <= 0xEF) {
            $expectedLength = 3;
        } elseif ($lead >= 0xF0 && $lead <= 0xF4) {
            $expectedLength = 4;
        } else {
            return $summary;
        }

        $availableLength = $length - $start;

        if ($availableLength >= $expectedLength) {
            return $summary;
        }

        $sequence = substr($summary, $start).substr($lookahead, 0, $expectedLength - $availableLength);

        if (strlen($sequence) !== $expectedLength || preg_match('//u', $sequence) !== 1) {
            return $summary;
        }

        return substr($summary, 0, $start);
    }

    /**
     * Attempts to rewind a message body and throws an exception on failure.
     *
     * The body of the message will only be rewound if a call to `tell()`
     * returns a value other than `0`.
     *
     * @param MessageInterface $message Message to rewind
     *
     * @throws \RuntimeException
     */
    public static function rewindBody(MessageInterface $message): void
    {
        $body = $message->getBody();

        if ($body->tell()) {
            $body->rewind();
        }
    }

    /**
     * Parses an HTTP message into an associative array.
     *
     * The array contains the `start-line` key containing the start line of the
     * message, `headers` key containing an associative array of header array
     * values, and a `body` key containing the body of the message.
     *
     * @param string $message HTTP request or response to parse.
     */
    public static function parseMessage(string $message): array
    {
        return MessageParser::parseMessage($message);
    }

    /**
     * Constructs a URI for an HTTP request message.
     *
     * The URI is composed from the start-line path and the `Host` header, using
     * `https` when the host's port is `443` and `http` otherwise. Without a
     * `Host` header, only the path is returned, with extra leading slashes
     * collapsed so an origin-form target cannot be parsed as a network-path
     * reference with its own authority. An `InvalidArgumentException` is thrown
     * when the `Host` header is invalid.
     *
     * @param string $path    Path from the start-line
     * @param array  $headers Array of headers (each value an array).
     */
    public static function parseRequestUri(string $path, array $headers): string
    {
        return MessageParser::parseRequestUri($path, $headers);
    }

    /**
     * Parses a request message string into a request object.
     *
     * The request-target must be in origin form, absolute form (without a
     * userinfo component), authority form (`CONNECT`), or asterisk form
     * (`OPTIONS`), and any `Host` header must be a single valid value;
     * otherwise an `InvalidArgumentException` is thrown. Non-origin-form
     * targets are preserved on the returned request via `withRequestTarget()`.
     *
     * @param string $message Request message string.
     */
    public static function parseRequest(string $message): RequestInterface
    {
        return MessageParser::parseRequest($message);
    }

    /**
     * Parses a response message string into a response object.
     *
     * @param string $message Response message string.
     */
    public static function parseResponse(string $message): ResponseInterface
    {
        return MessageParser::parseResponse($message);
    }
}
