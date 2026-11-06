<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

use Mailcow\Archive\Smtp\SmtpClient;
use Mailcow\Archive\Smtp\SmtpException;

/**
 * SmtpClient without network: records submit() and probe() calls, answers with the configured results.
 */
class FakeSmtpClient extends SmtpClient
{
    /** @var array[] submit() calls: host, port, from, rcpt, data */
    public array $submitted = [];
    /** @var array[] probe() calls */
    public array $probed = [];
    public ?SmtpException $error = null;
    /** @var string[] extensions probe() reports */
    public array $extensions = ['PIPELINING', 'SIZE', 'STARTTLS', '8BITMIME', 'SMTPUTF8'];
    public array $mx = [];

    public function submit(string $host, int $port, string $from, string $rcpt, string $data): string
    {
        if ($this->error !== null) {
            throw $this->error;
        }
        $this->submitted[] = compact('host', 'port', 'from', 'rcpt', 'data');
        return 'Q' . count($this->submitted);
    }

    public function probe(string $host, int $port, string $tls, string $username, string $password, string $from, string $rcpt): array
    {
        $this->probed[] = compact('host', 'port', 'tls', 'username', 'password', 'from', 'rcpt');
        if ($this->error !== null) {
            throw $this->error;
        }
        return $this->extensions;
    }

    public function mailHost(string $domain): string
    {
        return $this->mx[$domain] ?? $domain;
    }
}
