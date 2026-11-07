<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Model;

/**
 * Result of the policy check for one recipient: allowed, or refused by the send or receive part of a policy.
 */
class Decision
{
    private const TEXT = [
        'send' => 'Sending to this recipient is not allowed by the mail policy of the sender',
        'receive' => 'This recipient does not accept mail from this sender',
    ];

    private function __construct(public readonly bool $allowed, public readonly string $side = '', public readonly ?Policy $policy = null)
    {
    }

    public static function allow(): self
    {
        return new self(true);
    }

    /** $side send or receive */
    public static function deny(string $side, Policy $policy): self
    {
        return new self(false, $side, $policy);
    }

    /** Text of the rejection: the text of the policy or the default */
    public function text(): string
    {
        return ($this->policy !== null && $this->policy->rejectText !== '') ? $this->policy->rejectText : (self::TEXT[$this->side] ?? '');
    }

    /** Answer for the Postfix policy protocol */
    public function action(): string
    {
        return $this->allowed ? 'DUNNO' : '550 5.7.1 ' . $this->text();
    }
}
