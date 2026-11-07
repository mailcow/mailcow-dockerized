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
 * sender address belongs to. Receive part: the policies of the addressed mailbox (+tag and alias domains
 * resolved), of the addressed group and the `internal` flag of the addressed alias or group. Mail sent to an
 * alias or group reaches its members without their own policies. Every policy must allow the mail.
 *
 * Internal: a recipient in the domain of the policy or one of its alias domains; a sender logged in with a
 * mailbox of that domain. Entries of a local domain or address count for logged-in senders only, so they cannot
 * be forged; external entries are compared with the envelope sender. Dovecot submits Sieve redirects and
 * vacation replies of a mailbox without login on port 588: they count as sent by that mailbox.
 */
class PolicyEngine
{
    /** Ports of mailcow's own deliveries: quarantine release (590), BCC and archive copies (591) */
    private const INTERNAL_PORTS = [590, 591];
    /** Submission of SOGo and Dovecot, without login reachable from mailcow's own network only */
    private const SERVICE_PORT = 588;

    public function __construct(private LookupRepository $lookup)
    {
    }

    /** $login: SASL username, '' if not logged in; $port: smtpd port of the request, 0 if unknown */
    public function check(string $sender, string $recipient, string $login, int $port = 0): Decision
    {
        $sender = self::address($sender);
        $recipient = self::address($recipient);
        $login = self::address($login);
        if (in_array($port, self::INTERNAL_PORTS, true) || $recipient === '') {
            return Decision::allow();
        }
        $account = ($login !== '') ? $this->lookup->mailbox($login) : null;
        if ($login === '' && $port === self::SERVICE_PORT) {
            // Sieve redirects and vacation replies use the address of the mailbox, other mail of mailcow passes
            $account = $this->lookup->mailbox($this->resolve(self::untagged($sender)));
            if ($account === null) {
                return Decision::allow();
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
                fn(string $entry): bool => $this->senderMatches($entry, $sender, $account))) {
                return Decision::deny('receive', $policy);
            }
        }
        return Decision::allow();
    }

    /** Blocked entries first; then the mode decides */
    private function allows(string $mode, array $allow, array $block, bool $internal, callable $matches): bool
    {
        foreach ($block as $entry) {
            if ($matches($entry)) {
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
            if ($matches($entry)) {
                return true;
            }
        }
        return false;
    }

    /** @return Policy[] of the logged-in mailbox and of the mailbox it sends as */
    private function sendingPolicies(?array $account, string $sender): array
    {
        if ($account === null) {
            return [];
        }
        $policies = $this->lookup->policiesForMailbox($account['username']);
        $sentAs = $this->lookup->mailbox($this->resolve(self::untagged($sender)));
        if ($sentAs !== null && $sentAs['username'] !== $account['username']) {
            $policies = array_merge($policies, $this->lookup->policiesForMailbox($sentAs['username']));
        }
        return self::unique($policies);
    }

    /** @return Policy[] of the addressed mailbox, group and internal alias */
    private function receivingPolicies(string $recipient): array
    {
        $address = $this->resolve(self::untagged($recipient));
        $policies = [];
        if ($this->lookup->mailbox($address) !== null) {
            $policies = $this->lookup->policiesForMailbox($address);
        }
        $group = $this->lookup->groupByAddress($address);
        if ($group !== null) {
            $policies = array_merge($policies, $this->lookup->policiesForGroup($group));
        }
        $aliasDomain = $this->lookup->internalAlias($address);
        if ($aliasDomain !== null) {
            // the `internal` flag of an alias or group: only senders of its domain
            $internal = new Policy();
            $internal->domain = $aliasDomain;
            $internal->receiveMode = 'internal';
            $policies[] = $internal;
        }
        return self::unique($policies);
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
            return $this->lookup->groupAddress($id) === $address
                || ($this->lookup->mailbox($address) !== null && $this->lookup->isGroupMember($id, $address));
        }
        if (str_contains($entry, '@')) {
            return $this->resolve($entry) === $address;
        }
        return $this->resolveDomain(self::domainOf($address)) === $this->resolveDomain($entry);
    }

    private function senderMatches(string $entry, string $sender, ?array $account): bool
    {
        $address = $this->resolve(self::untagged($sender));
        if (str_starts_with($entry, 'group:')) {
            $id = (int)substr($entry, 6);
            return $account !== null && ($this->lookup->isGroupMember($id, $account['username']) || $this->lookup->groupAddress($id) === $address);
        }
        if (str_contains($entry, '@')) {
            // a local address only for a logged-in sender (Postfix checked that the login may use it)
            return $this->lookup->isLocalDomain(self::domainOf($entry))
                ? $account !== null && ($account['username'] === $this->resolve($entry) || $address === $this->resolve($entry))
                : self::untagged($sender) === $entry;
        }
        return $this->lookup->isLocalDomain($entry)
            ? $account !== null && ($account['domain'] === $this->resolveDomain($entry) || $this->resolveDomain(self::domainOf($address)) === $this->resolveDomain($entry))
            : self::domainOf($sender) === $entry;
    }

    /** Address in an alias domain as the address in its target domain */
    private function resolve(string $address): string
    {
        $domain = self::domainOf($address);
        $target = ($domain !== '') ? $this->lookup->aliasDomainTarget($domain) : null;
        return ($target === null) ? $address : substr($address, 0, strrpos($address, '@') + 1) . $target;
    }

    private function resolveDomain(string $domain): string
    {
        return ($domain === '') ? '' : ($this->lookup->aliasDomainTarget($domain) ?? $domain);
    }

    /** @param Policy[] $policies @return Policy[] without repeated policies */
    private static function unique(array $policies): array
    {
        $unique = [];
        foreach ($policies as $policy) {
            $unique[$policy->id ?? spl_object_id($policy)] = $policy;
        }
        return array_values($unique);
    }

    /** Lower case address without angle brackets; '' unless local@domain */
    private static function address(string $value): string
    {
        $value = strtolower(trim($value, " \t\r\n<>"));
        return preg_match('/^[^@\s,<>]+@[^@\s,<>]+$/', $value) ? $value : '';
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
