<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Service;

use Mailcow\Backup\Model\Settings;
use Mailcow\Backup\Repository\SettingsRepository;
use Mailcow\Backup\Service\SettingsService;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase
{
    private SettingsRepository&MockObject $repo;
    /** @var array<string, string> what the repository holds */
    private array $stored = [];
    /** @var array[] values passed to save() */
    private array $saved = [];
    /** Key pair of the runner (sealed boxes) */
    private string $keypair;

    protected function setUp(): void
    {
        $this->keypair = sodium_crypto_box_keypair();
        $this->stored = ['state_secrets_pubkey' => base64_encode(sodium_crypto_box_publickey($this->keypair))];
        $this->repo = $this->createMock(SettingsRepository::class);
        $this->repo->method('load')->willReturnCallback(fn(): array => $this->stored);
        $this->repo->method('save')->willReturnCallback(function (array $values): void {
            $this->saved[] = $values;
            $this->stored = array_merge($this->stored, $values);
        });
    }

    private function service(?Context $context = null): SettingsService
    {
        return new SettingsService($this->repo, $context ?? FakeContext::admin());
    }

    private function expectUserError(string|array $msg, callable $call): void
    {
        try {
            $call();
            $this->fail('UserError ' . json_encode($msg) . ' expected');
        } catch (UserError $e) {
            $this->assertSame($msg, $e->msg());
        }
    }

    /** A configured S3 target as the runner left it */
    private function s3(array $values = []): void
    {
        $this->stored = $values + ['target' => 's3', 's3_bucket' => 'backups', 's3_access_key' => 'AKIA1', 's3_secret_key' => 'SECRET',
            'password' => 'repo-password', 'password_confirmed' => '1', 'state_initialized' => '1', 'sftp_host_key' => 'ssh-ed25519 AAAA',
            'state_secrets_pubkey' => base64_encode(sodium_crypto_box_publickey($this->keypair))];
    }

    /** What the runner decrypts from a stored secret */
    private function open(string $stored): string|false
    {
        $this->assertStringStartsWith('sealed:v1:', $stored);
        return sodium_crypto_box_seal_open((string)base64_decode(substr($stored, strlen('sealed:v1:')), true), $this->keypair);
    }

    public function testSecretsNeverLeaveTheApi(): void
    {
        $this->s3(['state_repo_stats' => '{"total_size": 5}', 'state_runner_seen' => '1700000000', 'unknown' => 'x']);

        $settings = $this->service()->get()->toArray();

        $this->assertSame([1, 1, 0], [$settings['password_set'], $settings['s3_secret_key_set'], $settings['rest_password_set']]);
        $this->assertArrayNotHasKey('password', $settings);
        $this->assertArrayNotHasKey('s3_secret_key', $settings);
        $this->assertArrayNotHasKey('unknown', $settings);
        $this->assertSame([7, 1700000000, null, ['total_size' => 5], 1], [$settings['keep_daily'], $settings['state_runner_seen'],
            $settings['state_repo_size'], $settings['state_repo_stats'], $settings['configured']]);
        $this->assertStringNotContainsString('SECRET', json_encode($settings));
    }

    public function testValuesAreNormalized(): void
    {
        $settings = $this->service()->save([
            'target' => 'sftp', 'sftp_host' => 'u123.your-storagebox.de', 'sftp_port' => '23', 'sftp_user' => 'u123-sub1', 'sftp_path' => 'backups/mailcow/',
            's3_path' => '/mailcow/', 'schedule' => [['days' => [5, '1', 3, 1], 'time' => '3:05'], ['days' => [0], 'time' => '14:00'], ['days' => [1, 3, 5], 'time' => '03:05']], 'max_unused' => '5', 'notify_email' => "a@example.org; b@example.org\nA@example.org",
            'enabled' => ['0', '1'], 'include_fts' => false, 'keep_yearly' => 3, 'password' => 'long enough',
        ]);

        $this->assertSame(['sftp', 'backups/mailcow', 'mailcow', '[{"days":[1,3,5],"time":"03:05"},{"days":[0],"time":"14:00"}]', '5%',
            'a@example.org,b@example.org,A@example.org', '1', '0', '3'],
            array_map(fn(string $name): string => $settings->get($name),
                ['target', 'sftp_path', 's3_path', 'schedule', 'max_unused', 'notify_email', 'enabled', 'include_fts', 'keep_yearly']));
        $this->assertSame([[0, 14, 0], [1, 3, 5], [3, 3, 5], [5, 3, 5]], $settings->scheduleSlots());
        $this->assertSame([['days' => [1, 3, 5], 'time' => '03:05'], ['days' => [0], 'time' => '14:00']], $settings->toArray()['schedule']);
        // as JSON as well
        $this->assertSame('[{"days":[2],"time":"23:59"}]', $this->service()->save(['schedule' => '[{"days":[2],"time":"23:59"}]'])->get('schedule'));
        // secrets are stored sealed for the runner
        $this->assertSame('long enough', $this->open($this->stored['password']));
        // secrets as typed, spaces at the ends included
        $this->service()->save(['password' => ' spaced password ', 's3_secret_key' => '  key  ']);
        $this->assertSame([' spaced password ', '  key  '], [$this->open($this->stored['password']), $this->open($this->stored['s3_secret_key'])]);
        // only changed values are written
        $this->assertArrayNotHasKey('keep_daily', $this->saved[0]);
    }

    public static function invalidInput(): array
    {
        return [
            'target' => [['target' => 'ftp'], 'target'],
            'endpoint without scheme' => [['s3_endpoint' => 's3.example.org'], 's3_endpoint'],
            'endpoint with credentials' => [['s3_endpoint' => 'https://key:secret@s3.example.org'], 's3_endpoint'],
            'endpoint ftp' => [['s3_endpoint' => 'ftp://s3.example.org'], 's3_endpoint'],
            'endpoint with a path' => [['s3_endpoint' => 'https://s3.example.org/bucket'], 's3_endpoint'],
            'rest url with credentials' => [['rest_url' => 'https://user:pw@backup.example.org:8000/mailcow'], 'rest_url'],
            'rest url with query' => [['rest_url' => 'https://backup.example.org/?x=1'], 'rest_url'],
            'bucket' => [['s3_bucket' => 'my bucket'], 's3_bucket'],
            'bucket too long' => [['s3_bucket' => str_repeat('b', 64)], 's3_bucket'],
            's3 path up' => [['s3_path' => 'a/../b'], 's3_path'],
            'region' => [['s3_region' => 'eu central'], 's3_region'],
            'access key with space' => [['s3_access_key' => 'AK IA'], 's3_access_key'],
            'sftp host option' => [['sftp_host' => '-oProxyCommand=x'], 'sftp_host'],
            'sftp host with user' => [['sftp_host' => 'u1@host.example.org'], 'sftp_host'],
            'sftp port' => [['sftp_port' => '70000'], 'sftp_port'],
            'sftp user option' => [['sftp_user' => '-l'], 'sftp_user'],
            // the runner's character set
            'sftp user with @' => [['sftp_user' => 'u1@host'], 'sftp_user'],
            'sftp user with +' => [['sftp_user' => 'u1+x'], 'sftp_user'],
            'local path with ~' => [['local_path' => 'mailcow/~old'], 'local_path'],
            'local path with space' => [['local_path' => 'mail cow'], 'local_path'],
            'sftp path up' => [['sftp_path' => '/srv/../etc'], 'sftp_path'],
            'sftp path root' => [['sftp_path' => '/'], 'sftp_path'],
            'rest user with colon' => [['rest_user' => 'a:b'], 'rest_user'],
            'local path absolute' => [['local_path' => '/etc'], 'local_path'],
            'local path up' => [['local_path' => '../mailcow'], 'local_path'],
            'local path empty' => [['local_path' => ''], 'local_path'],
            'schedule time' => [['schedule' => [['days' => [1], 'time' => '24:00']]], 'schedule'],
            'schedule without time' => [['schedule' => [['days' => [1]]]], 'schedule'],
            'schedule day' => [['schedule' => [['days' => [1, 7], 'time' => '02:30']]], 'schedule'],
            'schedule without day' => [['schedule' => [['days' => [], 'time' => '02:30']]], 'schedule'],
            'schedule days as text' => [['schedule' => [['days' => '1,2', 'time' => '02:30']]], 'schedule'],
            'schedule nested day' => [['schedule' => [['days' => [[1]], 'time' => '02:30']]], 'schedule'],
            'schedule not a list' => [['schedule' => ['a' => ['days' => [1], 'time' => '02:30']]], 'schedule'],
            'schedule broken json' => [['schedule' => '[{"days":'], 'schedule'],
            'too many schedule entries' => [['schedule' => array_map(fn(int $h): array => ['days' => [1], 'time' => sprintf('%02d:00', $h % 24) . ''], range(0, 24))], 'schedule'],
            'keep' => [['keep_daily' => '1001'], 'keep_daily'],
            'keep hourly' => [['keep_hourly' => '1001'], 'keep_hourly'],
            'keep negative' => [['keep_weekly' => '-1'], 'keep_weekly'],
            'weekday' => [['prune_weekday' => '7'], 'prune_weekday'],
            'max unused' => [['max_unused' => '101%'], 'max_unused'],
            'max unused size' => [['max_unused' => '5G'], 'max_unused'],
            'check subset' => [['check_subset' => '366'], 'check_subset'],
            'limit upload' => [['limit_upload' => '-5'], 'limit_upload'],
            'notify' => [['notify_email' => 'a@example.org, nobody'], 'notify_email'],
            'flag' => [['enabled' => 'maybe'], 'enabled'],
            'list as text' => [['sftp_host' => ['a', 'b']], 'sftp_host'],
            'control character' => [['s3_secret_key' => "abc\ndef"], 's3_secret_key'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInput(array $input, string $name): void
    {
        $this->expectUserError(['backup_setting_invalid', $name], fn() => $this->service()->save($input));
        $this->assertSame([], $this->saved);
    }

    public function testPruneCanBeOff(): void
    {
        $settings = $this->service()->save(['prune_weekday' => '']);

        $this->assertSame(['', ''], [$settings->get('prune_weekday'), $settings->toArray()['prune_weekday']]);
        $this->assertSame(3, $this->service()->save(['prune_weekday' => '3'])->toArray()['prune_weekday']);
        $this->assertSame(0, (new Settings())->toArray()['prune_weekday']);
    }

    public function testSecretsNeedTheKeyOfTheRunner(): void
    {
        $this->stored = [];
        $this->expectUserError('backup_secrets_key_missing', fn() => $this->service()->save(['keep_daily' => '9', 'password' => 'long enough']));
        $this->assertSame([], $this->saved);
        // everything else can be saved before the backup container ran
        $this->assertSame('9', $this->service()->save(['keep_daily' => '9', 'password' => ''])->get('keep_daily'));
        $this->stored['state_secrets_pubkey'] = base64_encode('too short');
        $this->expectUserError('backup_secrets_key_missing', fn() => $this->service()->save(['rest_password' => 'x']));
    }

    public function testEverySecretIsSealedAnew(): void
    {
        $this->s3();

        $this->service()->save(['s3_secret_key' => 'NEW-SECRET', 'rest_password' => 'rest pw']);

        $this->assertSame(['NEW-SECRET', 'rest pw'], [$this->open($this->stored['s3_secret_key']), $this->open($this->stored['rest_password'])]);
        $this->assertStringNotContainsString('NEW-SECRET', json_encode($this->saved));
        $this->assertSame([1, 1], [$this->service()->get()->toArray()['s3_secret_key_set'], $this->service()->get()->toArray()['rest_password_set']]);
    }

    public function testATargetChangeIsReportedToTheAddressesOfBefore(): void
    {
        $this->s3(['notify_email' => 'old@example.org,boss@example.org', 's3_endpoint' => 'https://s3.example.org']);

        $this->service()->save(['target' => 'local', 'local_path' => 'elsewhere', 'notify_email' => 'new@example.org']);

        $change = json_decode($this->stored['state_target_changed'], true);
        $this->assertSame(['target' => 's3', 's3_endpoint' => 'https://s3.example.org', 's3_bucket' => 'backups', 's3_path' => '', 's3_region' => '',
            's3_access_key' => 'AKIA1', 's3_path_style' => '0'], $change['old_target']);
        $this->assertSame([['old@example.org', 'boss@example.org'], ['target', 'notify_email'], 'admin'],
            [$change['old_addresses'], $change['changed'], $change['changed_by']]);
        $this->assertIsInt($change['time']);
        $this->assertSame($change, $this->service()->get()->toArray()['state_target_changed']);
        // a second change before the runner mailed the first keeps the older state
        $this->service()->save(['local_path' => 'again']);
        $this->assertSame($change, json_decode($this->stored['state_target_changed'], true));
    }

    public function testNewAddressesAreReportedToTheOldOnes(): void
    {
        $this->s3(['notify_email' => 'old@example.org']);

        // new addresses first, then a new target: the original addresses hear about both
        $this->service()->save(['notify_email' => 'attacker@example.net']);
        $first = json_decode($this->stored['state_target_changed'], true);
        $this->service()->save(['target' => 'local']);
        $change = json_decode($this->stored['state_target_changed'], true);

        $this->assertSame([['old@example.org'], ['notify_email']], [$first['old_addresses'], $first['changed']]);
        $this->assertSame([['old@example.org'], ['notify_email', 'target'], 's3', $first['time']],
            [$change['old_addresses'], $change['changed'], $change['old_target']['target'], $change['time']]);
    }

    public function testWhatIsNoTargetChange(): void
    {
        // other settings, a value of another target
        $this->s3(['notify_email' => 'old@example.org']);
        $this->service()->save(['keep_daily' => '3', 'rest_url' => 'https://r.example.org', 'notify_email' => 'old@example.org']);
        $this->assertArrayNotHasKey('state_target_changed', $this->stored);
        // a new secret of the target is a change
        $this->service()->save(['s3_secret_key' => 'NEW']);
        $this->assertSame([['old@example.org'], ['target']], array_values(array_intersect_key(json_decode($this->stored['state_target_changed'], true),
            ['old_addresses' => 1, 'changed' => 1])));
        // the first addresses of a backup
        $this->s3();
        $this->service()->save(['notify_email' => 'first@example.org']);
        $this->assertArrayNotHasKey('state_target_changed', $this->stored);
        // a target that was not configured, or nobody to tell
        $this->stored = ['state_secrets_pubkey' => $this->stored['state_secrets_pubkey'], 'notify_email' => 'old@example.org'];
        $this->service()->save(['target' => 'local']);
        $this->s3();
        $this->service()->save(['target' => 'local']);
        $this->assertArrayNotHasKey('state_target_changed', $this->stored);
        // the runner's key cannot be set through the API
        $this->service()->save(['state_target_changed' => '', 'state_secrets_pubkey' => 'x']);
        $this->assertNotSame('x', $this->stored['state_secrets_pubkey']);
    }

    public function testShortRepositoryPassword(): void
    {
        $this->expectUserError(['backup_password_too_short', 8], fn() => $this->service()->save(['password' => 'short']));
    }

    public function testEmptySecretsAndRunnerValuesKeepTheStoredOnes(): void
    {
        $this->s3();

        $settings = $this->service()->save(['password' => '', 's3_secret_key' => '', 'state_initialized' => '0', 'sftp_public_key' => 'evil',
            'password_set' => 0, 'keep_daily' => '10']);

        $this->assertSame(['repo-password', 'SECRET', '1', 'ssh-ed25519 AAAA', '', '10'], array_map(fn(string $name): string => $settings->get($name),
            ['password', 's3_secret_key', 'state_initialized', 'sftp_host_key', 'sftp_public_key', 'keep_daily']));
        $this->assertSame([['keep_daily' => '10']], $this->saved);
    }

    public function testANewConnectionMustBeTestedAgain(): void
    {
        $this->s3();
        // values of another target do not touch the connection
        $this->assertSame('1', $this->service()->save(['rest_url' => 'https://backup.example.org:8000/mailcow'])->get('state_initialized'));
        $this->assertSame('0', $this->service()->save(['s3_secret_key' => 'NEW'])->get('state_initialized'));
        $this->s3();
        $this->assertSame('0', $this->service()->save(['s3_path_style' => '1'])->get('state_initialized'));
        $this->s3();
        $this->assertSame('0', $this->service()->save(['target' => 'local'])->get('state_initialized'));
    }

    public function testANewSftpHostDropsTheKnownHostKey(): void
    {
        $this->s3(['target' => 'sftp', 'sftp_host' => 'old.example.org', 'sftp_user' => 'u1', 'sftp_path' => 'repo']);
        $this->assertSame('ssh-ed25519 AAAA', $this->service()->save(['sftp_path' => 'repo2'])->get('sftp_host_key'));
        $this->assertSame('', $this->service()->save(['sftp_port' => '23'])->get('sftp_host_key'));
        $this->s3(['target' => 'sftp', 'sftp_host' => 'old.example.org', 'sftp_user' => 'u1', 'sftp_path' => 'repo']);
        $this->assertSame('', $this->service()->save(['sftp_host' => '192.0.2.10'])->get('sftp_host_key'));
    }

    public function testANewRepositoryPasswordNeedsANewConfirmation(): void
    {
        $this->s3();
        $settings = $this->service()->save(['password' => 'another password']);
        $this->assertSame(['0', '0'], [$settings->get('password_confirmed'), $settings->get('state_initialized')]);

        $this->s3();
        $this->assertSame('1', $this->service()->save(['password' => 'another password', 'password_confirmed' => '1'])->get('password_confirmed'));
    }

    public function testTheScheduleNeedsAConfiguredTarget(): void
    {
        $this->expectUserError('backup_not_configured', fn() => $this->service()->save(['enabled' => '1']));
        $this->s3();
        $this->assertSame('1', $this->service()->save(['enabled' => '1'])->get('enabled'));
        $this->expectUserError('backup_not_configured', fn() => $this->service()->save(['s3_bucket' => '']));
        // and at least one time
        $this->expectUserError(['backup_setting_invalid', 'schedule'], fn() => $this->service()->save(['schedule' => []]));
        $this->assertSame('[]', $this->service()->save(['enabled' => '0', 'schedule' => []])->get('schedule'));
    }

    public function testOnlyAdminsReadAndOnlyWritingAdminsSave(): void
    {
        $this->s3();
        $readOnly = new Context('admin', 'API', [], false, 'ro');

        $this->assertNotNull($this->service($readOnly)->get());
        $this->assertNull($this->service(FakeContext::domainAdmin([], ['firma.de']))->get());
        $this->assertNull($this->service(FakeContext::user('anna@firma.de'))->get());
        foreach ([$readOnly, FakeContext::domainAdmin([], ['firma.de']), FakeContext::user('anna@firma.de'), Context::system()] as $context) {
            $this->expectUserError('access_denied', fn() => $this->service($context)->save(['keep_daily' => '1']));
        }
        $this->assertSame([], $this->saved);
    }

    public function testConfiguredPerTarget(): void
    {
        $settings = Settings::fromStored(['password' => 'x', 'target' => 'local']);
        $this->assertTrue($settings->configured());
        $this->assertFalse(Settings::fromStored(['target' => 'local'])->configured());
        $this->assertFalse(Settings::fromStored(['password' => 'x', 'target' => 'rest'])->configured());
        $this->assertTrue(Settings::fromStored(['password' => 'x', 'target' => 'rest', 'rest_url' => 'https://r.example.org'])->configured());
        $this->assertFalse(Settings::fromStored(['password' => 'x', 'target' => 'sftp', 'sftp_host' => 'h', 'sftp_user' => 'u'])->configured());
    }
}
