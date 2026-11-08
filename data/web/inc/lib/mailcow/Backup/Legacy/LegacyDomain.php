<?php

declare(strict_types=1);

namespace Mailcow\Backup\Legacy;

use Redis;
use RuntimeException;

/**
 * What the legacy code keeps outside the database for domains and mailboxes: DKIM keys, rate limits and the domain
 * map in Redis (functions.dkim/ratelimit/mailbox.inc.php), and the SOGo restart through dockerapi. Redis and docker()
 * sit in protected methods so tests can replace them.
 */
class LegacyDomain
{
    public function __construct(private ?Redis $redis = null)
    {
    }

    /** @return ?array{selector: string, pubkey: string, privkey: string} the DKIM key of a domain */
    public function dkim(string $domain): ?array
    {
        $pubkey = $this->redis()->hGet('DKIM_PUB_KEYS', $domain);
        if (!is_string($pubkey) || $pubkey === '') {
            return null;
        }
        $selector = (string)$this->redis()->hGet('DKIM_SELECTORS', $domain);
        return ['selector' => $selector, 'pubkey' => $pubkey, 'privkey' => (string)$this->redis()->hGet('DKIM_PRIV_KEYS', $selector . '.' . $domain)];
    }

    /** Stores a DKIM key like dkim('import') does */
    public function addDkim(string $domain, string $selector, string $pubkey, string $privkey): void
    {
        $this->redis()->hSet('DKIM_PUB_KEYS', $domain, $pubkey);
        $this->redis()->hSet('DKIM_SELECTORS', $domain, $selector);
        $this->redis()->hSet('DKIM_PRIV_KEYS', $selector . '.' . $domain, $privkey);
    }

    /**
     * @param string[] $objects domains or mailboxes
     * @return array<string, string> object => rate limit ("10 / 1h") of those with one
     */
    public function rateLimits(array $objects): array
    {
        if (empty($objects)) {
            return [];
        }
        $values = $this->redis()->hMGet('RL_VALUE', array_values($objects));
        return array_filter(is_array($values) ? $values : [], fn(mixed $value): bool => is_string($value) && $value !== '');
    }

    public function setRateLimit(string $object, string $value): void
    {
        $this->redis()->hSet('RL_VALUE', $object, $value);
    }

    /** The domain is local for rspamd (legacy domain and alias domain add) */
    public function addDomainMap(string $domain): void
    {
        $this->redis()->hSet('DOMAIN_MAP', $domain, 1);
    }

    /**
     * SOGo reads its domains at start: restart it after a domain was added (like the "restart SOGo" option of the form)
     *
     * @return ?string null on success, else the error of dockerapi
     */
    public function restartSogo(): ?string
    {
        $result = json_decode((string)$this->dockerRestart('sogo-mailcow'), true);
        return (($result['type'] ?? '') === 'success') ? null : (string)($result['msg'] ?? 'dockerapi did not answer');
    }

    protected function redis(): Redis
    {
        return $this->redis ?? throw new RuntimeException('Redis is not connected');
    }

    protected function dockerRestart(string $service): mixed
    {
        return docker('post', $service, 'restart');
    }
}
