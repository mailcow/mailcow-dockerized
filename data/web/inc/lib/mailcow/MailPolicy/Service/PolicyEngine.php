<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Service;

use Mailcow\MailPolicy\Model\Decision;
use Mailcow\MailPolicy\Model\Policy;
use Mailcow\MailPolicy\Repository\LookupRepository;

/**
 * Policy check of one recipient, asked by Postfix at RCPT TO (data/conf/rspamd/dynmaps/mailpolicy.php).
 *
 * Send part: the policies of the logged-in mailbox (assigned to it or to its groups) and of the mailbox the
 * sender address belongs to. Receive part: what Postfix delivers the recipient to, looked up in the order of its
 * virtual alias maps (address, address without +tag, mailbox of an alias domain): the policies of the mailbox, of
 * the group address or of the owner of a temporary alias, and the `internal` flag of the alias (a catch-all alias
 * with the flag covers its whole domain). Mail sent to an alias or group reaches its members without their own
 * policies. Every policy must allow the mail.
 *
 * Internal: a recipient in the domain of the policy or one of its alias domains; a sender logged in with a
 * mailbox of that domain. Entries of a local domain or address count for logged-in senders only, so they cannot
 * be forged; block entries also match the envelope sender. External entries are compared with the envelope sender.
 * Addresses are compared the way Postfix finds them: lower case, without trailing dot, in their stored spelling
 * (MySQL ignores accents). Recipients routed through localhost (user%domain@localhost, domain!user@localhost,
 * user@domain@localhost) are refused, Postfix would deliver them to the routed address.
 *
 * Dovecot submits Sieve redirects, vacation replies and notifications on port 588 without login, SOGo sends
 * reminders with a service login: mail with the address of a mailbox counts as sent by that mailbox. Other mail of
 * these services (bounces redirected with the null sender, Sieve rejects) has no known sender: it is checked like
 * mail from outside and does not leave the mailcow while any policy restricts sending. Quota warnings pass.
 */
class PolicyEngine
{
    /** Ports of mailcow's own deliveries: quarantine release (590), BCC and archive copies (591) */
    private const INTERNAL_PORTS = [590, 591];
    /** Submission of SOGo and Dovecot, without login reachable from mailcow's own network only */
    private const SERVICE_PORT = 588;
    /** Domain of the Dovecot master login of mailcow's services (SOGo reminders) */
    private const SERVICE_LOGIN_DOMAIN = 'mailcow.local';
    /** HELO of the quota warning copies of Dovecot (quota_notify.py), configured by admins */
    private const QUOTA_HELO = 'quotanotification';
    /** Recipient without domain every server accepts (RFC 5321) */
    private const POSTMASTER = 'postmaster';
    /** Postfix delivers these destinations itself (mydestination), user%domain and domain!user route from there */
    private const LOCAL_DESTINATIONS = ['localhost', 'localhost.localdomain'];

    /** Lookups of the current check */
    private array $cache = [];

    public function __construct(private LookupRepository $lookup)
    {
    }

    /** $login: SASL username, '' if not logged in; $port: smtpd port of the request, 0 if unknown; $helo: HELO name */
    public function check(string $sender, string $recipient, string $login, int $port = 0, string $helo = ''): Decision
    {
        $this->cache = [];
        $recipient = self::address($recipient);
        if (in_array($port, self::INTERNAL_PORTS, true) || $recipient === '' || $recipient === self::POSTMASTER) {
            return Decision::allow();
        }
        if (self::isRouted($recipient)) {
            return Decision::routedAddress();
        }
        $recipient = $this->stored($recipient);
        $sender = $this->stored(self::address($sender));
        $login = self::address($login);
        $serviceLogin = self::domainOf($login) === self::SERVICE_LOGIN_DOMAIN;
        $account = null;
        if ($login !== '' && !$serviceLogin) {
            $account = $this->cached('mailbox', $login);
        } elseif ($serviceLogin || $port === self::SERVICE_PORT) {
            if ($port === self::SERVICE_PORT && !$serviceLogin && strtolower($helo) === self::QUOTA_HELO) {
                return Decision::allow();
            }
            // without a mailbox sender the sending mailbox is unknown
            $account = $this->senderMailbox($sender);
            if ($account === null && !$this->cached('isLocalDomain', self::domainOf($recipient)) && $this->lookup->hasSendRules()) {
                return Decision::deny('send');
            }
        }
        foreach ($this->sendingPolicies($account, $sender) as $policy) {
            if (!$this->allows($policy->sendMode, $policy->sendAllow, $policy->sendBlock, $this->isInternalRecipient($recipient, $policy->domain),
                fn(string $entry): bool => $this->recipientMatches($entry, $recipient))) {
                return Decision::deny('send', $policy);
            }
        }
        foreach ($this->receivingPolicies($recipient) as $policy) {
            if (!$this->allows($policy->receiveMode, $policy->receiveAllow, $policy->receiveBlock,
                $account !== null && $account['domain'] === $policy->domain,
                fn(string $entry, bool $block): bool => $this->senderMatches($entry, $sender, $account, $block))) {
                return Decision::deny('receive', $policy);
            }
        }
        return Decision::allow();
    }

    /** Blocked entries first; then the mode decides. $matches($entry, $block) */
    private function allows(string $mode, array $allow, array $block, bool $internal, callable $matches): bool
    {
        foreach ($block as $entry) {
            if ($matches($entry, true)) {
                return false;
            }
        }
        return match ($mode) {
            'internal' => $internal || $this->any($allow, $matches),
            'list' => $this->any($allow, $matches),
            default => true,
        };
    }

    private function any(array $entries, callable $matches): bool
    {
        foreach ($entries as $entry) {
            if ($matches($entry, false)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{username: string, domain: string}|null mailbox of the sender address */
    private function senderMailbox(string $sender): ?array
    {
        return str_contains($sender, '@') ? $this->cached('mailbox', $this->resolve(self::untagged($sender))) : null;
    }

    /** @return Policy[] of the logged-in mailbox and of the mailbox it sends as */
    private function sendingPolicies(?array $account, string $sender): array
    {
        if ($account === null) {
            return [];
        }
        $policies = $this->lookup->policiesForMailbox($account['username']);
        $sentAs = $this->senderMailbox($sender);
        if ($sentAs !== null && $sentAs['username'] !== $account['username']) {
            $policies = array_merge($policies, $this->lookup->policiesForMailbox($sentAs['username']));
        }
        return self::unique($policies);
    }

    /** @return Policy[] of what Postfix delivers the recipient to, see the class comment */
    private function receivingPolicies(string $recipient): array
    {
        // the address, then without +tag; an alias domain maps to the mailbox in its target domain and Postfix
        // looks up the result again with the +tag
        [$address, $target] = $this->aliasTarget([$recipient, self::untagged($recipient)]);
        $resolved = $this->resolve($recipient);
        if ($target === null && $resolved !== $recipient) {
            [$address, $target] = $this->aliasTarget([$resolved, self::untagged($resolved)]);
        }
        $address ??= self::untagged($resolved);
        if (($target['kind'] ?? '') === 'temporary') {
            $address = $target['owner'];
        }
        $policies = ($this->cached('mailbox', $address) !== null) ? $this->lookup->policiesForMailbox($address) : [];
        $group = $this->lookup->groupByAddress($address);
        if ($group !== null) {
            $policies = array_merge($policies, $this->lookup->policiesForGroup($group));
        }
        // the `internal` flag of an alias or group: only senders of its domain
        if (!empty($target['internal'])) {
            $policies[] = $this->internalRule($target['domain']);
        }
        foreach (array_unique([self::domainOf($recipient), $this->resolveDomain(self::domainOf($recipient))]) as $domain) {
            if ($this->lookup->internalCatchAll($domain)) {
                $policies[] = $this->internalRule($domain);
            }
        }
        return self::unique($policies);
    }

    /** @return array{0: ?string, 1: ?array} the first key Postfix' virtual alias maps find, with what they find */
    private function aliasTarget(array $keys): array
    {
        foreach (array_unique($keys) as $key) {
            $target = $this->lookup->aliasTarget($key);
            if ($target !== null) {
                return [$key, $target];
            }
        }
        return [null, null];
    }

    /** Receive rule of the `internal` flag */
    private function internalRule(string $domain): Policy
    {
        $policy = new Policy();
        $policy->domain = $this->resolveDomain($domain);
        $policy->receiveMode = 'internal';
        return $policy;
    }

    private function isInternalRecipient(string $recipient, string $domain): bool
    {
        return $this->resolveDomain(self::domainOf($recipient)) === $domain;
    }

    private function recipientMatches(string $entry, string $recipient): bool
    {
        $address = $this->resolve(self::untagged($recipient));
        if (str_starts_with($entry, 'group:')) {
            $id = (int)substr($entry, 6);
            return $this->cached('groupAddress', $id) === $address
                || ($this->cached('mailbox', $address) !== null && $this->cached('isGroupMember', $id, $address));
        }
        if (str_contains($entry, '@')) {
            return $this->resolve($entry) === $address;
        }
        return $this->resolveDomain(self::domainOf($address)) === $this->resolveDomain($entry);
    }

    /** $block: a block entry, which also matches the envelope sender (refusing a forged sender is no risk) */
    private function senderMatches(string $entry, string $sender, ?array $account, bool $block): bool
    {
        $address = $this->resolve(self::untagged($sender));
        // the sender address of a local entry only counts when logged in: Postfix checked that the login may use it
        $useAddress = $account !== null || $block;
        if (str_starts_with($entry, 'group:')) {
            $id = (int)substr($entry, 6);
            return ($account !== null && $this->cached('isGroupMember', $id, $account['username']))
                || ($useAddress && ($this->cached('groupAddress', $id) === $address
                    || ($this->cached('mailbox', $address) !== null && $this->cached('isGroupMember', $id, $address))));
        }
        if (str_contains($entry, '@')) {
            return $this->cached('isLocalDomain', self::domainOf($entry))
                ? ($account !== null && $account['username'] === $this->resolve($entry)) || ($useAddress && $address === $this->resolve($entry))
                : self::untagged($sender) === $entry;
        }
        return $this->cached('isLocalDomain', $entry)
            ? ($account !== null && $account['domain'] === $this->resolveDomain($entry))
                || ($useAddress && $this->resolveDomain(self::domainOf($address)) === $this->resolveDomain($entry))
            : self::domainOf($sender) === $entry;
    }

    /**
     * Stored spelling of an address of this mailcow: Postfix finds chéf@ as chef@ (MySQL ignores accents). Only
     * addresses with non-ASCII characters can differ, as they are already in lower case.
     */
    private function stored(string $address): string
    {
        if (!preg_match('/[\x80-\xff]/', $address) || !str_contains($address, '@')) {
            return $address;
        }
        foreach (array_unique([$address, self::untagged($address)]) as $key) {
            $stored = $this->cached('storedAddress', $key);
            if ($stored !== null) {
                return $stored;
            }
        }
        $domain = $this->cached('storedDomain', self::domainOf($address));
        return ($domain === null) ? $address : substr($address, 0, strrpos($address, '@') + 1) . $domain;
    }

    /** Address in an alias domain as the address in its target domain */
    private function resolve(string $address): string
    {
        $domain = self::domainOf($address);
        $target = ($domain !== '') ? $this->cached('aliasDomainTarget', $domain) : null;
        return ($target === null) ? $address : substr($address, 0, strrpos($address, '@') + 1) . $target;
    }

    private function resolveDomain(string $domain): string
    {
        return ($domain === '') ? '' : ($this->cached('aliasDomainTarget', $domain) ?? $domain);
    }

    /** A lookup, done once per check: long lists ask the same domains and groups again */
    private function cached(string $method, int|string ...$args): mixed
    {
        $key = $method . "\0" . implode("\0", $args);
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->lookup->$method(...$args);
        }
        return $this->cache[$key];
    }

    /** @param Policy[] $policies @return Policy[] without repeated policies */
    private static function unique(array $policies): array
    {
        $unique = [];
        foreach ($policies as $policy) {
            $unique[$policy->id ?? 'internal:' . $policy->domain] = $policy;
        }
        return array_values($unique);
    }

    /** Lower case, without angle brackets, quotes and trailing dot of the domain, as Postfix resolves it; '' = null sender */
    private static function address(string $value): string
    {
        $value = strtolower(trim($value, " \t\r\n<>"));
        $at = strrpos($value, '@');
        return ($at === false) ? $value : str_replace('"', '', substr($value, 0, $at)) . '@' . rtrim(substr($value, $at + 1), '.');
    }

    /**
     * Postfix would deliver the recipient elsewhere: no domain, or routing through a local destination
     * (localhost, the IP of the server) with %, ! or a second @ in the local part
     */
    private static function isRouted(string $address): bool
    {
        $at = strrpos($address, '@');
        if ($at === false || $at === 0 || $at === strlen($address) - 1) {
            return true;
        }
        $domain = substr($address, $at + 1);
        return (in_array($domain, self::LOCAL_DESTINATIONS, true) || str_starts_with($domain, '['))
            && strpbrk(substr($address, 0, $at), '@%!') !== false;
    }

    private static function untagged(string $address): string
    {
        $at = strrpos($address, '@');
        $pos = ($at === false) ? false : strpos(substr($address, 0, $at), '+');
        return ($pos === false || $pos === 0) ? $address : substr($address, 0, $pos) . substr($address, $at);
    }

    private static function domainOf(string $address): string
    {
        $at = strrpos($address, '@');
        return ($at === false) ? '' : substr($address, $at + 1);
    }
}
