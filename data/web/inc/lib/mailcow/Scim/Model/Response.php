<?php

declare(strict_types=1);

namespace Mailcow\Scim\Model;

/**
 * A SCIM HTTP response; the body is sent as application/scim+json.
 */
class Response
{
    /** @param string[] $headers */
    public function __construct(public readonly int $status, public readonly ?array $body = null, public readonly array $headers = [])
    {
    }
}
