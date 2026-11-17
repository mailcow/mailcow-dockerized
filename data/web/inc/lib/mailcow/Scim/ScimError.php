<?php

declare(strict_types=1);

namespace Mailcow\Scim;

use RuntimeException;

/**
 * An error answered with a SCIM error response (RFC 7644 3.12): HTTP status, detail and optional scimType.
 * Services throw it, ScimController turns it into the response.
 */
class ScimError extends RuntimeException
{
    /** @param string[] $headers extra response headers, e.g. 'Allow: GET' */
    public function __construct(public readonly int $status, string $detail, public readonly ?string $scimType = null,
        public readonly array $headers = [])
    {
        parent::__construct($detail);
    }

    public static function notFound(string $detail = 'Resource not found'): self
    {
        return new self(404, $detail);
    }

    public static function invalidValue(string $detail): self
    {
        return new self(400, $detail, 'invalidValue');
    }

    public static function uniqueness(string $detail): self
    {
        return new self(409, $detail, 'uniqueness');
    }

    public static function mutability(string $detail): self
    {
        return new self(400, $detail, 'mutability');
    }
}
