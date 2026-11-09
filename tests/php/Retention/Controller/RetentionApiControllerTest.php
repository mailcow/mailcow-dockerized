<?php

declare(strict_types=1);

namespace Mailcow\Tests\Retention\Controller;

use Mailcow\Core\ApiResponse;
use Mailcow\Retention\Controller\RetentionApiController;
use Mailcow\Retention\Model\Rule;
use Mailcow\Retention\Repository\RuleRepository;
use Mailcow\Retention\Service\RuleService;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\TestCase;

class RetentionApiControllerTest extends TestCase
{
    public function testASingleRuleCarriesItsLastRun(): void
    {
        $rule = new Rule();
        $rule->id = 3;
        $rule->domain = 'firma.de';
        $rule->name = 'Papierkorb';
        $run = ['rule_id' => 3, 'created' => '2026-11-09 03:30:00', 'mailboxes' => 2, 'matched' => 5, 'deleted' => 0, 'errors' => 0, 'simulated' => 1];
        $repo = $this->createMock(RuleRepository::class);
        $repo->method('find')->willReturn($rule);
        $repo->method('lastRuns')->with([3])->willReturn([3 => $run]);
        $api = new RetentionApiController(new RuleService($repo, FakeContext::admin()), new ApiResponse());

        $this->assertSame($run, $api->getRule('3')['last_run']);
        $this->assertSame([], $api->getRule('x'));
    }
}
