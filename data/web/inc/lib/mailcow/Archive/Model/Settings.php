<?php

declare(strict_types=1);

namespace Mailcow\Archive\Model;

/**
 * The archive target (table `archive_settings`): archive copies of accepted mails go by SMTP to `address`,
 * through the Postfix queue (table `archive_transport`, service archive_smtp in master.cf).
 */
class Settings
{
    public const TLS_MODES = ['encrypt', 'verify', 'may'];
    /** Metadata headers of a copy: archivecow (X-Archive-*, signed), envelope (sender/recipients), none */
    public const PROFILES = ['archivecow', 'envelope', 'none'];
    /** Envelope sender of copies; Postfix drops every notice to it (archive_discard), nothing reaches a real sender */
    public const SENDER = 'archive-bounce@localhost';

    /** rspamd hands accepted mails to the archive */
    public bool $enabled = false;
    /** Address the archive receives copies at */
    public string $address = '';
    /** SMTP server of the archive; empty: MX of the address domain */
    public string $host = '';
    public int $port = 25;
    public string $tls = 'encrypt';
    /** SMTP AUTH at the archive, optional */
    public string $username = '';
    /** Never part of toArray() */
    public string $password = '';
    public string $profile = 'archivecow';
    /** Key of X-Archive-Signature (profile archivecow), never part of toArray() */
    public string $signingKey = '';
    /** Header names of the envelope profile */
    public string $headerFrom = 'X-Envelope-From';
    public string $headerRcpt = 'X-Envelope-To';

    /** Copies can be sent: an address, and a signing key for archivecow */
    public function canDeliver(): bool
    {
        return $this->address !== '' && ($this->profile !== 'archivecow' || $this->signingKey !== '');
    }

    /** Next hop for Postfix: [host]:port, else the domain of the address (MX lookup), with the port when not 25 */
    public function nexthop(): string
    {
        if ($this->host !== '') {
            $host = filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'ipv6:' . $this->host : $this->host;
            return '[' . $host . ']:' . $this->port;
        }
        $domain = substr($this->address, (int)strrpos($this->address, '@') + 1);
        return ($this->port === 25) ? $domain : $domain . ':' . $this->port;
    }

    /** API representation (GET archive/settings), without secrets */
    public function toArray(): array
    {
        return [
            'enabled' => (int)$this->enabled,
            'address' => $this->address,
            'host' => $this->host,
            'port' => $this->port,
            'tls' => $this->tls,
            'username' => $this->username,
            'has_password' => (int)($this->password !== ''),
            'profile' => $this->profile,
            'has_signing_secret' => (int)($this->signingKey !== ''),
            'header_from' => $this->headerFrom,
            'header_rcpt' => $this->headerRcpt,
        ];
    }
}
