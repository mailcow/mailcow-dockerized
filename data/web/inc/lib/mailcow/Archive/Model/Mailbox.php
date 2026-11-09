<?php

declare(strict_types=1);

namespace Mailcow\Archive\Model;

/**
 * A mailbox that receives mail (regular kind, active 1 or 2, in an active domain that is no backup MX)
 * with its archive settings: the mailbox attribute `archive` ('1', '0' or 'inherit') and the domain default.
 */
class Mailbox
{
    public const INHERIT = 'inherit';

    public function __construct(
        public readonly string $username,
        public readonly string $domain,
        public readonly string $archive,
        public readonly bool $domainArchive,
        public readonly string $name = '',
        public readonly int $active = 1
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            strtolower((string)$row['username']),
            strtolower((string)$row['domain']),
            self::setting($row['archive'] ?? null),
            (int)($row['domain_archive'] ?? 0) === 1,
            (string)($row['name'] ?? ''),
            (int)($row['active'] ?? 1)
        );
    }

    /** Mailbox setting: '1', '0' (also as number or boolean), anything else (also a missing attribute) is 'inherit' */
    public static function setting(mixed $value): string
    {
        $value = is_bool($value) ? (int)$value : $value;
        $value = is_scalar($value) ? (string)$value : '';
        return in_array($value, ['0', '1'], true) ? $value : self::INHERIT;
    }

    /** Archiving applies by the mailbox setting, 'inherit' follows the domain (without the global switch) */
    public static function effective(mixed $archive, mixed $domainArchive): bool
    {
        $archive = self::setting($archive);
        return ($archive === self::INHERIT) ? (int)$domainArchive === 1 : $archive === '1';
    }

    public function archived(): bool
    {
        return self::effective($this->archive, $this->domainArchive);
    }

    /** Why the mailbox is archived: its own setting or the domain default */
    public function source(): string
    {
        return ($this->archive === '1') ? 'mailbox' : 'domain';
    }

    /** Entry of GET archive/mailboxes */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'domain' => $this->domain,
            'name' => $this->name,
            'active' => $this->active,
            'source' => $this->source(),
        ];
    }
}
