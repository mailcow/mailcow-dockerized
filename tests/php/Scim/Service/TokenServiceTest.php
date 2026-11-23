<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Service;

use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Repository\TokenRepository;
use Mailcow\Scim\ScimError;
use Mailcow\Scim\Service\TokenService;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    private TokenRepository&MockObject $repo;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(TokenRepository::class);
        $this->repo->method('domainExists')->willReturnCallback(fn(string $d): bool => $d === 'example.org');
    }

    private function service(?Context $context = null): TokenService
    {
        // ip_acl() of functions.inc.php, reduced to exact matches
        return new class($this->repo, $context ?? FakeContext::admin()) extends TokenService {
            protected function ipAllowed(string $ip, array $networks): bool
            {
                return in_array($ip, $networks, true);
            }
        };
    }

    public function testCreateStoresOnlyTheHash(): void
    {
        $stored = null;
        $this->repo->expects($this->once())->method('insert')->willReturnCallback(function (Token $token) use (&$stored): int {
            $stored = $token;
            return 1;
        });
        [$token, $value] = $this->service()->create(['description' => 'Entra', 'domain_restriction' => 'Example.org',
            'allow_from' => "192.0.2.1\n2001:db8::/64", 'default_template' => 'Staff',
            'mappers' => ['', 'sales', 'x'], 'templates' => ['', 'Sales', ''], 'active' => ['0', '1']]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $value);
        $this->assertSame(hash('sha256', $value), $stored->hash);
        $this->assertSame('example.org', $token->domainRestriction);
        $this->assertSame(['192.0.2.1', '2001:db8::/64'], $token->allowFrom);
        // only complete mapping rows are kept
        $this->assertSame([['sales'], ['Sales']], [$token->mappers, $token->templates]);
        $this->assertSame(1, $token->active);
        // renames are off by default, the alias is kept by default
        $this->assertSame([0, 1], [$token->allowRename, $token->renameAlias]);
        $this->assertArrayNotHasKey('token_hash', $token->toArray());
    }

    public function testScopesFailClosed(): void
    {
        // a non-string domain or network must not end up as "all domains" / "any IP"
        foreach ([[['domain_restriction' => ['example.org']], 'domain_invalid'], [['allow_from' => [['10.0.0.0/8']]], 'network_host_invalid']] as [$input, $key]) {
            try {
                $this->service()->create($input + ['description' => 'x']);
                $this->fail("UserError $key expected");
            } catch (UserError $e) {
                $this->assertSame($key, $e->key);
            }
        }
    }

    public function testAnUnchangedRestrictionToADeletedDomainIsKept(): void
    {
        $token = Token::fromRow(['id' => 3, 'domain_restriction' => 'gone.org']);
        $this->repo->method('find')->willReturn($token);
        // the edit form sends the stored value back, its domain no longer exists
        $this->service()->update(3, ['description' => 'new', 'domain_restriction' => 'gone.org']);
        $this->assertSame(['new', 'gone.org'], [$token->description, $token->domainRestriction]);
    }

    public function testRenameSwitches(): void
    {
        $token = Token::fromRow(['id' => 3]);
        $this->repo->method('find')->willReturn($token);
        // form: hidden 0 before the checkbox
        $this->service()->update(3, ['allow_rename' => ['0', '1'], 'rename_alias' => ['0']]);
        $this->assertSame([1, 0], [$token->allowRename, $token->renameAlias]);
        $this->assertSame([1, 0], [$token->toArray()['allow_rename'], $token->toArray()['rename_alias']]);
    }

    public function testValidation(): void
    {
        foreach ([[['domain_restriction' => 'nope.org'], 'domain_not_found'], [['group_domain' => 'nope.org'], 'domain_not_found'],
            [['allow_from' => '192.0.2.0/33'], 'network_host_invalid'], [['description' => str_repeat('x', 256)], 'scim_token_description_invalid']] as [$input, $key]) {
            try {
                $this->service()->create($input);
                $this->fail('UserError expected');
            } catch (UserError $e) {
                $this->assertSame($key, $e->key);
            }
        }
    }

    public function testOnlyAdminsManageTokens(): void
    {
        $this->repo->expects($this->never())->method('insert');
        foreach ([FakeContext::domainAdmin(), Context::system(), new Context('admin', 'API', [], false, 'ro')] as $context) {
            $this->assertSame([], $this->service($context)->all());
            try {
                $this->service($context)->create([]);
                $this->fail('AccessDenied expected');
            } catch (UserError $e) {
                $this->assertSame('access_denied', $e->key);
            }
        }
    }

    public function testAuthenticate(): void
    {
        $token = Token::fromRow(['id' => 1, 'allow_from' => '192.0.2.1']);
        $this->repo->method('findActiveByHash')->willReturnCallback(fn(string $hash): ?Token => ($hash === hash('sha256', 'secret')) ? $token : null);

        $this->assertSame($token, $this->service()->authenticate('Bearer secret', '192.0.2.1'));
        foreach ([['', '192.0.2.1'], ['Basic secret', '192.0.2.1'], ['Bearer wrong', '192.0.2.1'], ['Bearer secret', '192.0.2.2']] as [$header, $ip]) {
            try {
                $this->service()->authenticate($header, $ip);
                $this->fail('401 expected');
            } catch (ScimError $e) {
                $this->assertSame(401, $e->status);
                $this->assertStringStartsWith('WWW-Authenticate: Bearer', $e->headers[0]);
            }
        }
        // no IPs = any
        $token->allowFrom = [];
        $this->assertSame($token, $this->service()->authenticate('bearer secret', '198.51.100.7'));
    }
}
