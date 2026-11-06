<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Service;

use Mailcow\Archive\Service\OwnerResolver;
use Mailcow\Tests\Support\FakeArchiveLookup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OwnerResolverTest extends TestCase
{
    private FakeArchiveLookup $lookup;

    protected function setUp(): void
    {
        $this->lookup = new FakeArchiveLookup();
        // archived domain firma.de, domain without archiving other.de
        $this->lookup->mailboxes = [
            'anna@firma.de' => ['inherit', 1],
            'bernd@firma.de' => ['inherit', 1],
            'buchhaltung@firma.de' => ['inherit', 1],
            'br@firma.de' => ['0', 1],
            'olga@other.de' => ['inherit', 0],
            'otto@other.de' => ['1', 0],
        ];
        // mailboxes have alias self rows
        foreach (array_keys($this->lookup->mailboxes) as $username) {
            $this->lookup->aliases[$username] = $username;
        }
    }

    private function owners(array $rcpts, string $from = 'sender@extern.example', string $user = '', bool $bcc = true): array
    {
        return (new OwnerResolver($this->lookup))->resolve($from, $rcpts, $user, $bcc)['owners'];
    }

    public static function incoming(): array
    {
        return [
            'mailbox' => [['anna@firma.de'], ['anna@firma.de']],
            'upper case and brackets' => [['<Anna@FIRMA.de>'], ['anna@firma.de']],
            'tag' => [['anna+news@firma.de'], ['anna@firma.de']],
            'external' => [['someone@extern.example'], []],
            'mailbox setting off' => [['br@firma.de'], []],
            'domain without archiving' => [['olga@other.de'], []],
            'mailbox setting on in a domain without archiving' => [['otto@other.de'], ['otto@other.de']],
            'several recipients, sorted and unique' => [['bernd@firma.de', 'anna@firma.de', 'anna+x@firma.de'], ['anna@firma.de', 'bernd@firma.de']],
        ];
    }

    #[DataProvider('incoming')]
    public function testIncomingRecipients(array $rcpts, array $owners): void
    {
        $this->assertSame($owners, $this->owners($rcpts));
    }

    public function testAliasExpandsAllBranches(): void
    {
        $this->lookup->aliases['team@firma.de'] = 'sales@firma.de,support@firma.de';
        $this->lookup->aliases['sales@firma.de'] = 'anna@firma.de';
        $this->lookup->aliases['support@firma.de'] = 'bernd@firma.de,someone@extern.example';

        $this->assertSame(['anna@firma.de', 'bernd@firma.de'], $this->owners(['team@firma.de']));
    }

    public function testAliasKeepingACopyAndLoops(): void
    {
        $this->lookup->aliases['anna@firma.de'] = 'anna@firma.de,bernd@firma.de';
        $this->lookup->aliases['loop1@firma.de'] = 'loop2@firma.de';
        $this->lookup->aliases['loop2@firma.de'] = 'loop1@firma.de,olga@other.de,otto@other.de';

        $this->assertSame(['anna@firma.de', 'bernd@firma.de'], $this->owners(['anna@firma.de']));
        $this->assertSame(['otto@other.de'], $this->owners(['loop1@firma.de']));
    }

    public function testCatchAllOnlyForAddressesWithoutOwnEntry(): void
    {
        $this->lookup->aliases['@firma.de'] = 'buchhaltung@firma.de';
        $this->lookup->aliases['old@firma.de'] = 'gone@firma.de';

        $this->assertSame(['buchhaltung@firma.de'], $this->owners(['unknown@firma.de']));
        $this->assertSame(['anna@firma.de'], $this->owners(['anna@firma.de']));
        // a target without entry of its own gets the catch-all too, like Postfix
        $this->assertSame(['buchhaltung@firma.de'], $this->owners(['old@firma.de']));
    }

    public function testAliasDomain(): void
    {
        $this->lookup->aliasDomains['firma.com'] = 'firma.de';
        $this->lookup->aliases['@firma.com'] = 'bernd@firma.de';

        $this->assertSame(['anna@firma.de'], $this->owners(['anna@firma.com']));
        $this->assertSame(['anna@firma.de'], $this->owners(['anna+tag@firma.com']));
        $this->assertSame(['bernd@firma.de'], $this->owners(['nobody@firma.com']));
    }

    public function testRecipientMapsRewriteFirst(): void
    {
        $this->lookup->recipientMaps['anna@extern.example'] = 'anna@firma.de';
        $this->lookup->recipientMaps['@alt.example'] = '@firma.de';

        $this->assertSame(['anna@firma.de'], $this->owners(['anna@extern.example']));
        $this->assertSame(['bernd@firma.de'], $this->owners(['bernd@alt.example']));
    }

    public function testSpamAliasResourceAndLocalTargets(): void
    {
        $this->lookup->spamAliases['x7k2@firma.de'] = 'anna@firma.de';
        $this->lookup->resources = ['room@firma.de'];
        $this->lookup->aliases['@firma.de'] = 'bernd@firma.de';
        $this->lookup->aliases['learn@firma.de'] = 'spam@localhost';

        $this->assertSame(['anna@firma.de'], $this->owners(['x7k2@firma.de']));
        $this->assertSame([], $this->owners(['room@firma.de']));
        $this->assertSame([], $this->owners(['learn@firma.de']));
    }

    public function testBccDestinationsOnlyWhenRspamdSentTheCopy(): void
    {
        $this->lookup->bcc['rcpt']['olga@other.de'] = 'buchhaltung@firma.de';
        $this->lookup->bcc['sender']['@extern.example'] = 'bernd@firma.de';

        $this->assertSame(['bernd@firma.de', 'buchhaltung@firma.de'], $this->owners(['olga+tag@other.de']));
        $this->assertSame([], $this->owners(['olga@other.de'], bcc: false));
    }

    public function testOutgoingSaslUserAndSentAsMailbox(): void
    {
        $this->lookup->aliases['info@firma.de'] = 'anna@firma.de,bernd@firma.de';
        $this->lookup->aliasDomains['firma.com'] = 'firma.de';
        $ext = ['someone@extern.example'];

        $this->assertSame(['anna@firma.de'], $this->owners($ext, 'anna@firma.de', 'anna@firma.de'));
        $this->assertSame(['anna@firma.de', 'buchhaltung@firma.de'], $this->owners($ext, 'buchhaltung@firma.de', 'anna@firma.de'));
        $this->assertSame(['anna@firma.de', 'buchhaltung@firma.de'], $this->owners($ext, 'buchhaltung+x@firma.com', 'Anna@firma.de'));
        // an alias as sender is no mailbox
        $this->assertSame(['anna@firma.de'], $this->owners($ext, 'info@firma.de', 'anna@firma.de'));
        // the sent-as mailbox has archiving off
        $this->assertSame(['anna@firma.de'], $this->owners($ext, 'br@firma.de', 'anna@firma.de'));
    }

    public function testSenderWithoutLoginNeverOwns(): void
    {
        $this->assertSame([], $this->owners(['someone@extern.example'], 'anna@firma.de'));
        $this->assertSame([], $this->owners(['someone@extern.example'], 'anna@firma.de', 'x1@mailcow.local'));
    }

    public function testDirection(): void
    {
        $resolver = new OwnerResolver($this->lookup);

        $this->assertSame('in', $resolver->resolve('x@extern.example', ['anna@firma.de'], '', false)['direction']);
        $this->assertSame('out', $resolver->resolve('anna@firma.de', ['x@extern.example'], 'anna@firma.de', false)['direction']);
        $this->assertSame('internal', $resolver->resolve('anna@firma.de', ['olga@other.de'], 'anna@firma.de', false)['direction']);
    }
}
