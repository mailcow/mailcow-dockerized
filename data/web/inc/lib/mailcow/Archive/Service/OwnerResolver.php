<?php

declare(strict_types=1);

namespace Mailcow\Archive\Service;

use Mailcow\Archive\Model\Mailbox;
use Mailcow\Archive\Repository\LookupRepository;

/**
 * Finds the mailboxes a mail belongs to in the archive, following the way Postfix delivers it.
 *
 * Incoming: each envelope recipient goes through recipient_maps (one rewrite) and the virtual alias expansion:
 * alias rows (incl. catch-alls and groups), resources (discarded), spam aliases and mailboxes of alias domains,
 * each tried as u+ext@d, u@d and @d, recursively. The local mailboxes at the end receive the mail. BCC map
 * destinations count as recipients when rspamd sends the BCC copy (it bypasses rspamd).
 *
 * Outgoing: the SASL user and the mailbox the user sent as (its address, with +tag or in an alias domain);
 * Postfix already checked that the login may use that sender. Without a login the sender never counts, so a
 * forged local sender cannot make a mailbox an owner.
 *
 * Only mailboxes with archiving become owners.
 */
class OwnerResolver
{
    /** virtual_alias_recursion_limit and virtual_alias_expansion_limit of Postfix */
    private const MAX_DEPTH = 1000;
    private const MAX_ADDRESSES = 1000;

    public function __construct(private LookupRepository $lookup)
    {
    }

    /**
     * @param string[] $rcpts SMTP envelope recipients
     * @param bool $bcc rspamd sent the BCC copies of this mail
     * @return array{owners: string[], direction: string} sorted usernames; direction in, out or internal
     */
    public function resolve(string $from, array $rcpts, string $user, bool $bcc): array
    {
        $from = self::address($from);
        $incoming = [];
        foreach ($rcpts as $rcpt) {
            $rcpt = self::address($rcpt);
            if ($rcpt !== '') {
                $this->deliver($rcpt, $incoming);
            }
        }
        if ($bcc) {
            foreach ($this->bccDestinations($from, $rcpts) as $destination) {
                $this->deliver($destination, $incoming);
            }
        }
        $outgoing = $this->senders($from, self::address($user));
        $owners = [];
        foreach (array_merge(array_values($incoming), array_values($outgoing)) as $mailbox) {
            if ($mailbox->archived()) {
                $owners[$mailbox->username] = true;
            }
        }
        $owners = array_keys($owners);
        sort($owners);
        $direction = empty($outgoing) ? 'in' : (empty($incoming) ? 'out' : 'internal');
        return ['owners' => $owners, 'direction' => $direction];
    }

    /** Adds the local mailboxes a recipient ends in to $mailboxes (username => Mailbox) */
    private function deliver(string $address, array &$mailboxes): void
    {
        $seen = [];
        $this->expand($this->canonical($address), 0, $mailboxes, $seen);
    }

    /** recipient_maps: first matching key form, one rewrite */
    private function canonical(string $address): string
    {
        foreach (self::keys($address) as $key) {
            $target = self::target($this->lookup->recipientMap($key));
            if ($target !== '') {
                return self::rewrite($address, $key, $target);
            }
        }
        return $address;
    }

    private function expand(string $address, int $depth, array &$mailboxes, array &$seen): void
    {
        if ($depth > self::MAX_DEPTH || count($seen) >= self::MAX_ADDRESSES || isset($seen[$address])) {
            return;
        }
        $seen[$address] = true;
        $targets = $this->virtual($address);
        if ($targets === null) {
            $this->final($address, $mailboxes);
            return;
        }
        foreach ($targets as $target) {
            // an address that expands to itself is delivered as it is
            if ($target === $address) {
                $this->final($address, $mailboxes);
            } else {
                $this->expand($target, $depth + 1, $mailboxes, $seen);
            }
        }
    }

    /** Targets from the first key form a virtual alias map knows, null if none does */
    private function virtual(string $address): ?array
    {
        if (self::split($address)[1] === 'localhost') {
            // null@, spam@, ham@localhost: local delivery of Postfix, never a mailbox
            return [];
        }
        foreach (self::keys($address) as $key) {
            $targets = $this->virtualLookup($key);
            if ($targets !== null) {
                return array_map(fn(string $target): string => self::rewrite($address, $key, $target), $targets);
            }
        }
        return null;
    }

    /** virtual_alias_maps in the order of main.cf: alias, resource, spam alias, alias domain mailbox */
    private function virtualLookup(string $key): ?array
    {
        $domainKey = str_starts_with($key, '@');
        $targets = self::targets($this->lookup->aliasGoto($key));
        if ($targets === null && !$domainKey && $this->lookup->isResource($key)) {
            $targets = ['null@localhost'];
        }
        $targets ??= self::targets($this->lookup->spamAliasGoto($key));
        if ($targets === null && !$domainKey) {
            [$local, $domain] = self::split($key);
            $targets = self::targets($this->lookup->aliasDomainMailbox($local, $domain));
        }
        return $targets;
    }

    /** A final address: a mailbox (as u+ext@d or u@d) or anything else, which is not ours */
    private function final(string $address, array &$mailboxes): void
    {
        foreach (array_unique([$address, self::untagged($address)]) as $candidate) {
            $mailbox = $this->lookup->mailbox($candidate);
            if ($mailbox !== null) {
                $mailboxes[$mailbox->username] = $mailbox;
                return;
            }
        }
    }

    /** BCC destinations of rspamd: rcpt maps for every recipient and its domain, sender maps for MAIL FROM and its domain */
    private function bccDestinations(string $from, array $rcpts): array
    {
        $destinations = [];
        foreach ($rcpts as $rcpt) {
            $rcpt = self::address($rcpt);
            if ($rcpt !== '') {
                $destinations[] = $this->lookup->bccDestination(self::untagged($rcpt), 'rcpt');
                $destinations[] = $this->lookup->bccDestination('@' . self::split($rcpt)[1], 'rcpt');
            }
        }
        if ($from !== '') {
            $destinations[] = $this->lookup->bccDestination(self::untagged($from), 'sender');
            $destinations[] = $this->lookup->bccDestination('@' . self::split($from)[1], 'sender');
        }
        return array_values(array_unique(array_filter(array_map(fn(?string $address): string => self::address($address), $destinations))));
    }

    /** Mailboxes on the sending side: the SASL user and the mailbox the user sent as */
    private function senders(string $from, string $user): array
    {
        $mailbox = ($user !== '') ? $this->lookup->mailbox($user) : null;
        if ($mailbox === null) {
            return [];
        }
        $senders = [$mailbox->username => $mailbox];
        $sentAs = ($from !== '') ? $this->sentAs(self::untagged($from)) : null;
        if ($sentAs !== null) {
            $senders[$sentAs->username] = $sentAs;
        }
        return $senders;
    }

    /** Mailbox a sender address belongs to: the mailbox itself or the mailbox behind an alias domain address, not aliases */
    private function sentAs(string $address): ?Mailbox
    {
        $mailbox = $this->lookup->mailbox($address);
        if ($mailbox === null) {
            [$local, $domain] = self::split($address);
            $username = $this->lookup->aliasDomainMailbox($local, $domain);
            $mailbox = ($username !== null) ? $this->lookup->mailbox(strtolower($username)) : null;
        }
        return $mailbox;
    }

    /** Lookup keys in the order of Postfix: u+ext@d, u@d, @d */
    private static function keys(string $address): array
    {
        $keys = [$address, self::untagged($address), '@' . self::split($address)[1]];
        return array_values(array_unique($keys));
    }

    /**
     * Result of a lookup for $address under $key: '@domain' keeps the local part, a match without the extension
     * passes it on (propagate_unmatched_extensions).
     */
    private static function rewrite(string $address, string $key, string $target): string
    {
        [$local] = self::split($address);
        if (str_starts_with($target, '@')) {
            return $local . $target;
        }
        $extension = self::extension($local);
        if ($key === $address || $extension === '') {
            return $target;
        }
        [$targetLocal, $targetDomain] = self::split($target);
        return ($targetDomain === 'localhost' || self::extension($targetLocal) !== '')
            ? $target
            : $targetLocal . '+' . $extension . '@' . $targetDomain;
    }

    /** Comma separated lookup result; null when empty (not found) */
    private static function targets(?string $value): ?array
    {
        $targets = array_values(array_filter(array_map(fn(string $target): string => self::target($target), explode(',', (string)$value))));
        return empty($targets) ? null : $targets;
    }

    /** Address or '@domain' from a lookup result, lower case; '' if invalid */
    private static function target(?string $value): string
    {
        $value = strtolower(trim((string)$value));
        return preg_match('/^[^@\s,<>]*@[^@\s,<>]+$/', $value) ? $value : '';
    }

    /** Lower case address without angle brackets; '' unless local@domain */
    private static function address(mixed $value): string
    {
        $value = is_string($value) ? strtolower(trim($value, " \t\r\n<>")) : '';
        return preg_match('/^[^@\s,<>]+@[^@\s,<>]+$/', $value) ? $value : '';
    }

    private static function untagged(string $address): string
    {
        [$local, $domain] = self::split($address);
        $pos = strpos($local, '+');
        return ($pos === false || $pos === 0) ? $address : substr($local, 0, $pos) . '@' . $domain;
    }

    /** Address extension after the recipient delimiter "+" */
    private static function extension(string $local): string
    {
        $pos = strpos($local, '+');
        return ($pos === false || $pos === 0) ? '' : substr($local, $pos + 1);
    }

    /** [local part, domain] of an address or '@domain' */
    private static function split(string $address): array
    {
        $at = strrpos($address, '@');
        return ($at === false) ? [$address, ''] : [substr($address, 0, $at), substr($address, $at + 1)];
    }
}
