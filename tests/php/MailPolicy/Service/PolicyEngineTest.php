<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailPolicy\Service;

use Mailcow\MailPolicy\Model\Policy;
use Mailcow\MailPolicy\Service\PolicyEngine;
use Mailcow\Tests\Support\FakeMailPolicyLookup;
use PHPUnit\Framework\TestCase;

class PolicyEngineTest extends TestCase
{
    private FakeMailPolicyLookup $lookup;
    private PolicyEngine $engine;

    protected function setUp(): void
    {
        $this->lookup = new FakeMailPolicyLookup();
        $this->lookup->domains = ['firma.de', 'tochter.de'];
        $this->lookup->aliasDomains = ['firma.com' => 'firma.de'];
        $this->lookup->mailboxes = [
            'azubi@firma.de' => 'firma.de',
            'kollege@firma.de' => 'firma.de',
            'chef@firma.de' => 'firma.de',
            'info@firma.de' => 'firma.de',
            'andere@tochter.de' => 'tochter.de',
        ];
        $this->lookup->groups = [
            7 => ['address' => 'azubis@firma.de', 'members' => ['azubi@firma.de']],
            8 => ['address' => 'vorstand@firma.de', 'members' => ['chef@firma.de']],
        ];
        $this->engine = new PolicyEngine($this->lookup);
    }

    private static function policy(int $id, array $values): Policy
    {
        $policy = new Policy();
        $policy->id = $id;
        $policy->domain = 'firma.de';
        $policy->name = 'policy ' . $id;
        foreach ($values as $property => $value) {
            $policy->$property = $value;
        }
        return $policy;
    }

    /** [allowed, side] of a check */
    private function check(string $sender, string $recipient, string $login = '', int $port = 587): array
    {
        $decision = $this->engine->check($sender, $recipient, $login, $port);
        return [$decision->allowed, $decision->side];
    }

    public function testWithoutPoliciesEverythingPasses(): void
    {
        $this->assertSame([true, ''], $this->check('x@extern.example', 'azubi@firma.de'));
        $this->assertSame('DUNNO', $this->engine->check('azubi@firma.de', 'x@extern.example', 'azubi@firma.de')->action());
    }

    public function testSendOnlyInternalWithAllowedAndBlockedEntries(): void
    {
        $this->lookup->mailboxPolicies['azubi@firma.de'] = [self::policy(1, [
            'sendMode' => 'internal', 'sendAllow' => ['partner.de', 'kunde@extern.example'], 'sendBlock' => ['chef@firma.de'],
        ])];

        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'kollege@firma.de', 'azubi@firma.de'));
        // alias domain and sub-address are internal
        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'Kollege+x@firma.com', 'azubi@firma.de'));
        $this->assertSame([false, 'send'], $this->check('azubi@firma.de', 'x@extern.example', 'azubi@firma.de'));
        // another domain of the same mailcow is not internal
        $this->assertSame([false, 'send'], $this->check('azubi@firma.de', 'andere@tochter.de', 'azubi@firma.de'));
        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'jemand@partner.de', 'azubi@firma.de'));
        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'kunde@extern.example', 'azubi@firma.de'));
        $this->assertSame([false, 'send'], $this->check('azubi@firma.de', 'chef@firma.de', 'azubi@firma.de'));
        // the rules belong to the login: without it nothing is sent as azubi
        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'x@extern.example'));
    }

    public function testSendOnlyListedRecipientsThroughAGroup(): void
    {
        $this->lookup->groupPolicies[7] = [self::policy(2, ['sendMode' => 'list', 'sendAllow' => ['group:8']])];

        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'vorstand@firma.de', 'azubi@firma.de'));
        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'chef@firma.de', 'azubi@firma.de'));
        $this->assertSame([false, 'send'], $this->check('azubi@firma.de', 'kollege@firma.de', 'azubi@firma.de'));
        // not a member: no rule
        $this->assertSame([true, ''], $this->check('kollege@firma.de', 'x@extern.example', 'kollege@firma.de'));
    }

    public function testSendingAsAnotherMailboxAppliesItsPolicyToo(): void
    {
        $this->lookup->mailboxPolicies['info@firma.de'] = [self::policy(3, ['sendMode' => 'internal'])];

        $this->assertSame([false, 'send'], $this->check('info@firma.de', 'x@extern.example', 'chef@firma.de'));
        $this->assertSame([true, ''], $this->check('chef@firma.de', 'x@extern.example', 'chef@firma.de'));
    }

    public function testReceiveOnlyInternalCannotBeForged(): void
    {
        $this->lookup->mailboxPolicies['azubi@firma.de'] = [self::policy(4, ['receiveMode' => 'internal', 'receiveAllow' => ['partner.de'],
            'receiveBlock' => ['chef@firma.de']])];

        $this->assertSame([false, 'receive'], $this->check('x@extern.example', 'azubi@firma.de', '', 25));
        // a forged internal sender is external
        $this->assertSame([false, 'receive'], $this->check('kollege@firma.de', 'azubi@firma.de', '', 25));
        $this->assertSame([true, ''], $this->check('kollege@firma.de', 'azubi+news@firma.com', 'kollege@firma.de'));
        $this->assertSame([false, 'receive'], $this->check('andere@tochter.de', 'azubi@firma.de', 'andere@tochter.de'));
        // an external domain on the list is compared with the envelope sender
        $this->assertSame([true, ''], $this->check('jemand@partner.de', 'azubi@firma.de', '', 25));
        $this->assertSame([false, 'receive'], $this->check('chef@firma.de', 'azubi@firma.de', 'chef@firma.de'));
        // null sender (bounces) is external
        $this->assertSame([false, 'receive'], $this->check('', 'azubi@firma.de', '', 25));
    }

    public function testListedLocalSendersMustBeLoggedIn(): void
    {
        $this->lookup->mailboxPolicies['chef@firma.de'] = [self::policy(5, ['receiveMode' => 'list',
            'receiveAllow' => ['kollege@firma.de', 'group:7', 'tochter.de']])];

        $this->assertSame([false, 'receive'], $this->check('kollege@firma.de', 'chef@firma.de', '', 25));
        $this->assertSame([true, ''], $this->check('kollege@firma.de', 'chef@firma.de', 'kollege@firma.de'));
        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'chef@firma.de', 'azubi@firma.de'));
        $this->assertSame([false, 'receive'], $this->check('azubi@firma.de', 'chef@firma.de', '', 25));
        $this->assertSame([true, ''], $this->check('andere@tochter.de', 'chef@firma.de', 'andere@tochter.de'));
        $this->assertSame([false, 'receive'], $this->check('andere@tochter.de', 'chef@firma.de', '', 25));
    }

    public function testGroupAddressAndMembersAsRecipients(): void
    {
        $this->lookup->groupPolicies[8] = [self::policy(6, ['receiveMode' => 'internal', 'receiveBlock' => ['group:7']])];

        $this->assertSame([false, 'receive'], $this->check('x@extern.example', 'vorstand@firma.de', '', 25));
        $this->assertSame([true, ''], $this->check('kollege@firma.de', 'vorstand@firma.de', 'kollege@firma.de'));
        // the policy of a group applies to its members as well
        $this->assertSame([false, 'receive'], $this->check('x@extern.example', 'chef@firma.de', '', 25));
        $this->assertSame([false, 'receive'], $this->check('azubi@firma.de', 'chef@firma.de', 'azubi@firma.de'));
    }

    public function testInternalFlagOfAliasesAndGroups(): void
    {
        $this->lookup->internalAliases['team@firma.de'] = 'firma.de';

        $this->assertSame([false, 'receive'], $this->check('x@extern.example', 'team@firma.de', '', 25));
        // the old rspamd rule let forged senders of the domain pass
        $this->assertSame([false, 'receive'], $this->check('kollege@firma.de', 'team@firma.com', '', 25));
        $this->assertSame([true, ''], $this->check('kollege@firma.de', 'team@firma.de', 'kollege@firma.de'));
        $this->assertSame([false, 'receive'], $this->check('andere@tochter.de', 'team@firma.de', 'andere@tochter.de'));
    }

    public function testTheStrictestPolicyWins(): void
    {
        $this->lookup->mailboxPolicies['azubi@firma.de'] = [self::policy(9, ['sendMode' => 'all'])];
        $this->lookup->groupPolicies[7] = [self::policy(10, ['sendMode' => 'internal', 'rejectText' => 'Azubis senden nur intern'])];

        $decision = $this->engine->check('azubi@firma.de', 'x@extern.example', 'azubi@firma.de', 587);
        $this->assertSame(['send', 10, '550 5.7.1 Azubis senden nur intern'], [$decision->side, $decision->policy->id, $decision->action()]);
    }

    public function testDefaultTextsAndOwnDeliveries(): void
    {
        $this->lookup->mailboxPolicies['azubi@firma.de'] = [self::policy(11, ['sendMode' => 'internal', 'receiveMode' => 'internal'])];

        $this->assertSame('550 5.7.1 Sending to this recipient is not allowed by the mail policy of the sender',
            $this->engine->check('azubi@firma.de', 'x@extern.example', 'azubi@firma.de', 587)->action());
        $this->assertSame('550 5.7.1 This recipient does not accept mail from this sender',
            $this->engine->check('x@extern.example', 'azubi@firma.de', '', 25)->action());
        // quarantine release (590) and BCC/archive copies (591) are not checked
        $this->assertSame([true, ''], $this->check('x@extern.example', 'azubi@firma.de', '', 590));
        $this->assertSame([true, ''], $this->check('x@extern.example', 'azubi@firma.de', '', 591));
    }

    public function testDovecotSubmissionsCountAsTheMailbox(): void
    {
        $this->lookup->mailboxPolicies['azubi@firma.de'] = [self::policy(12, ['sendMode' => 'internal'])];
        $this->lookup->mailboxPolicies['chef@firma.de'] = [self::policy(13, ['receiveMode' => 'internal'])];
        $this->lookup->internalAliases['team@firma.de'] = 'firma.de';

        // a Sieve redirect of azubi to an external address follows its send rules
        $this->assertSame([false, 'send'], $this->check('azubi+x@firma.de', 'x@extern.example', '', 588));
        $this->assertSame([true, ''], $this->check('azubi@firma.de', 'chef@firma.de', '', 588));
        $this->assertSame([true, ''], $this->check('kollege@firma.de', 'team@firma.de', '', 588));
        // notifications of mailcow (e.g. quota warnings) are no mailbox
        $this->assertSame([true, ''], $this->check('quota-warning@localhost', 'chef@firma.de', '', 588));
        // on other ports the sender address is no login
        $this->assertSame([false, 'receive'], $this->check('kollege@firma.de', 'team@firma.de', '', 25));
    }
}
