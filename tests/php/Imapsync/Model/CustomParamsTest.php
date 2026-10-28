<?php

declare(strict_types=1);

namespace Mailcow\Tests\Imapsync\Model;

use Mailcow\Core\UserError;
use Mailcow\Imapsync\Model\CustomParams;
use Mailcow\Imapsync\Model\Job;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomParamsTest extends TestCase
{
    private const ALLOWLIST = [
        'debug' => false,
        'folder' => true,
        'sslargs1' => '^SSL_verify_mode=[01]$',
        'tilde' => 'a~b',
        'broken' => 5,
    ];

    public function testEmptyInputMeansNoOptions(): void
    {
        $this->assertSame('[]', CustomParams::toJson(null, self::ALLOWLIST));
        $this->assertSame('[]', CustomParams::toJson('', self::ALLOWLIST));
        $this->assertSame('[]', CustomParams::toJson([], self::ALLOWLIST));
    }

    public function testValidPairsAreNormalisedAndEncoded(): void
    {
        $json = CustomParams::toJson([
            ['o' => ' --Debug ', 'v' => ''],
            ['o' => 'folder', 'v' => "INBOX/Sub\0 äö"],
            ['o' => 'sslargs1', 'v' => 'SSL_verify_mode=0'],
            ['o' => '', 'v' => '  '],
        ], self::ALLOWLIST);

        $this->assertSame([
            ['o' => 'debug', 'v' => ''],
            ['o' => 'folder', 'v' => 'INBOX/Sub äö'],
            ['o' => 'sslargs1', 'v' => 'SSL_verify_mode=0'],
        ], json_decode($json, true));
        $this->assertSame('[{"o":"debug","v":""},{"o":"folder","v":"INBOX/Sub äö"},{"o":"sslargs1","v":"SSL_verify_mode=0"}]', $json);
    }

    public function testFlagWithWhitespaceValueIsStoredWithoutValue(): void
    {
        $this->assertSame('[{"o":"debug","v":""}]', CustomParams::toJson([['o' => 'debug', 'v' => ' ']], self::ALLOWLIST));
    }

    public static function invalidInputs(): array
    {
        return [
            'string instead of list' => ['--debug', 'imapsync_custom_params_format'],
            'pair is no array' => [['debug'], 'imapsync_custom_params_format'],
            'option is array' => [[['o' => ['x'], 'v' => '']], 'imapsync_custom_params_format'],
            'value is array' => [[['o' => 'folder', 'v' => ['x']]], 'imapsync_custom_params_format'],
            'unknown option' => [[['o' => '<b>logfile', 'v' => '/etc/passwd']], ['imapsync_custom_param_invalid', '&lt;b&gt;logfile']],
            'value without option' => [[['o' => '', 'v' => 'x']], ['imapsync_custom_param_invalid', '']],
            'flag with value' => [[['o' => 'debug', 'v' => '1']], ['imapsync_custom_param_value', 'debug']],
            'value option without value' => [[['o' => 'folder', 'v' => ' ']], ['imapsync_custom_param_value', 'folder']],
            'regex mismatch' => [[['o' => 'sslargs1', 'v' => 'SSL_ca_file=/etc/x']], ['imapsync_custom_param_value', 'sslargs1']],
            'regex must match whole value' => [[['o' => 'sslargs1', 'v' => "SSL_verify_mode=1\n"]], ['imapsync_custom_param_value', 'sslargs1']],
            'regex empty value' => [[['o' => 'sslargs1', 'v' => '']], ['imapsync_custom_param_value', 'sslargs1']],
            'malformed rule' => [[['o' => 'broken', 'v' => 'x']], ['imapsync_custom_param_invalid', 'broken']],
            'value too long' => [[['o' => 'folder', 'v' => str_repeat('a', 1001)]], 'imapsync_custom_params_format'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejected(mixed $input, string|array $msg): void
    {
        try {
            CustomParams::toJson($input, self::ALLOWLIST);
            $this->fail('UserError expected');
        } catch (UserError $e) {
            $this->assertSame($msg, $e->msg());
        }
    }

    public function testRegexDelimiterInRuleIsEscaped(): void
    {
        $this->assertSame('[{"o":"tilde","v":"a~b"}]', CustomParams::toJson([['o' => 'tilde', 'v' => 'a~b']], self::ALLOWLIST));
    }

    public function testAtMostOneHundredPairs(): void
    {
        $pairs = array_fill(0, 100, ['o' => 'debug', 'v' => '']);
        $this->assertCount(100, json_decode(CustomParams::toJson($pairs, self::ALLOWLIST), true));

        $this->expectException(UserError::class);
        $this->expectExceptionMessage('imapsync_custom_params_format');
        CustomParams::toJson(array_merge($pairs, [['o' => 'debug', 'v' => '']]), self::ALLOWLIST);
    }

    public static function storedValues(): array
    {
        return [
            'pairs' => ['[{"o":"folder","v":"a\\/b"}]'],
            'NULL' => [null],
            'old command line string' => ['--debug --folder INBOX'],
            'JSON object' => ['{"o":"debug"}'],
            'list of non-pairs' => ['[1,"x",{"v":"no option"}]'],
        ];
    }

    #[DataProvider('storedValues')]
    public function testStoredValuesPassThroughUnchanged(?string $stored): void
    {
        $this->assertSame($stored, Job::fromRow(['id' => 1, 'custom_params' => $stored])->toArray()['custom_params']);
    }

    public function testAllowlistDropsMalformedEntries(): void
    {
        $allowlist = CustomParams::allowlist([
            '--Folder' => true,
            'debug' => false,
            'sslargs1' => '^x$',
            '1abc' => true,
            'a-b' => true,
            'empty' => '',
            'number' => 1,
        ], ['default' => true]);

        $this->assertSame(['folder' => true, 'debug' => false, 'sslargs1' => '^x$'], $allowlist);
    }

    public function testAllowlistFallsBackToDefaults(): void
    {
        $this->assertSame(['default' => true], CustomParams::allowlist([], ['default' => true]));
        $this->assertSame(['default' => true], CustomParams::allowlist(['bad name' => true], ['default' => true]));
    }

    public function testOldWhitelistFormatIsIgnored(): void
    {
        $errorLog = ini_set('error_log', '/dev/null');
        try {
            $allowlist = CustomParams::allowlist(['whitelist' => ['debug'], 'folder' => true], ['default' => true]);
        } finally {
            ini_set('error_log', (string)$errorLog);
        }

        $this->assertSame(['folder' => true], $allowlist);
    }
}
