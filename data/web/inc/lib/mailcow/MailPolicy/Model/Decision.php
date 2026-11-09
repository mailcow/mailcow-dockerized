<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Model;

/**
 * Result of the policy check for one recipient: allowed, refused by the send or receive part of a policy, or refused
 * because the recipient is written in a form the check cannot follow (routing through another address).
 */
class Decision
{
    private const TEXT = [
        'send' => 'Sending to this recipient is not allowed by the mail policy of the sender',
        'receive' => 'This recipient does not accept mail from this sender',
        'address' => 'This form of the recipient address is not allowed by the mail policy',
    ];

    private function __construct(public readonly bool $allowed, public readonly string $side = '', public readonly ?Policy $policy = null)
    {
    }

    public static function allow(): self
    {
        return new self(true);
    }

    /** $side send or receive; without $policy the default text is used */
    public static function deny(string $side, ?Policy $policy = null): self
    {
        return new self(false, $side, $policy);
    }

    /** The recipient routes through another address (user%domain, domain!user, a second @) */
    public static function routedAddress(): self
    {
        return new self(false, 'address');
    }

    /** Text of the rejection: the text of the policy or the default */
    public function text(): string
    {
        return ($this->policy !== null && $this->policy->rejectText !== '') ? $this->policy->rejectText : self::defaultText($this->side);
    }

    public static function defaultText(string $side): string
    {
        return self::TEXT[$side] ?? '';
    }

    /** Answer for the Postfix policy protocol */
    public function action(): string
    {
        return $this->allowed ? 'DUNNO' : '550 5.7.1 ' . $this->text();
    }
}
