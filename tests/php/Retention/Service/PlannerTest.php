<?php

declare(strict_types=1);

namespace Mailcow\Tests\Retention\Service;

use Mailcow\Retention\Model\Rule;
use Mailcow\Retention\Service\Planner;
use PHPUnit\Framework\TestCase;

class PlannerTest extends TestCase
{
    private static function rule(int $id, array $folders, int $days, array $values = []): Rule
    {
        $rule = new Rule();
        $rule->id = $id;
        $rule->name = 'rule ' . $id;
        $rule->folders = $folders;
        $rule->days = $days;
        foreach ($values as $property => $value) {
            $rule->$property = $value;
        }
        return $rule;
    }

    /** @return string[] folder/match:days:rule id in the order of the plan */
    private static function plan(array $candidates): array
    {
        return array_map(fn(array $action): string => $action['folder'] . '/' . $action['match'] . ':' . $action['days'] . ':' . $action['rule_id'],
            Planner::actions($candidates));
    }

    public function testTheMailboxRuleWinsOverGroupAndDomain(): void
    {
        $this->assertSame(['trash/folder:90:3', 'trash/subfolders:90:3'], self::plan([
            ['rule' => self::rule(1, ['trash'], 30), 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(2, ['trash'], 10), 'level' => Planner::LEVEL_GROUP],
            ['rule' => self::rule(3, ['trash'], 90), 'level' => Planner::LEVEL_MAILBOX],
        ]));
    }

    public function testAMoreSpecificAssignmentWinsAlsoWithAllFolders(): void
    {
        // the group rule for all folders is more specific than the domain rule for the trash, which never wins
        $this->assertSame(['all/all:365:2'], self::plan([
            ['rule' => self::rule(1, ['trash'], 30), 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(2, ['all'], 365), 'level' => Planner::LEVEL_GROUP],
        ]));
    }

    public function testNamedFoldersComeBeforeAllFoldersOnTheSameLevel(): void
    {
        $this->assertSame(['trash/folder:30:2', 'folder:Newsletter/folder:7:3', 'trash/subfolders:30:2', 'all/all:365:1'], self::plan([
            ['rule' => self::rule(1, ['all'], 365), 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(2, ['trash'], 30), 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(3, ['folder:Newsletter'], 7), 'level' => Planner::LEVEL_DOMAIN],
        ]));
    }

    public function testTheFolderItselfBeforePatternsBeforeSubfolders(): void
    {
        // INBOX/Newsletter: its own rule; other subfolders of the inbox: INBOX/* before the subfolders of the inbox rule
        $this->assertSame(['inbox/folder:365:1', 'folder:INBOX/Newsletter/folder:7:3', 'folder:INBOX/*/pattern:30:2', 'inbox/subfolders:365:1'], self::plan([
            ['rule' => self::rule(1, ['inbox'], 365), 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(2, ['folder:INBOX/*'], 30), 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(3, ['folder:INBOX/Newsletter'], 7), 'level' => Planner::LEVEL_DOMAIN],
        ]));
    }

    public function testOverlappingFoldersAreDecidedPerFolder(): void
    {
        // * also matches the trash: the trash rule keeps the trash itself, * the other folders
        $this->assertSame(['trash/folder:365:3', 'folder:*/pattern:7:1', 'trash/subfolders:365:3'], self::plan([
            ['rule' => self::rule(1, ['folder:*'], 7, ['mode' => 'delete']), 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(3, ['trash'], 365), 'level' => Planner::LEVEL_DOMAIN],
        ]));
    }

    public function testOnATieTheLongerPeriodAndThenTheLowerIdWin(): void
    {
        $this->assertSame(['junk/folder:60:2', 'trash/folder:30:3', 'junk/subfolders:60:2', 'trash/subfolders:30:3'], self::plan([
            ['rule' => self::rule(1, ['junk'], 30), 'level' => Planner::LEVEL_GROUP],
            ['rule' => self::rule(2, ['junk'], 60), 'level' => Planner::LEVEL_GROUP],
            ['rule' => self::rule(4, ['trash'], 30), 'level' => Planner::LEVEL_GROUP],
            ['rule' => self::rule(3, ['trash'], 30), 'level' => Planner::LEVEL_GROUP],
        ]));
    }

    public function testARuleAssignedTwiceCountsWithItsBestLevel(): void
    {
        $rule = self::rule(1, ['folder:Alt'], 30);
        $this->assertSame(['folder:Alt/folder:30:1', 'all/all:90:2'], self::plan([
            ['rule' => $rule, 'level' => Planner::LEVEL_DOMAIN],
            ['rule' => self::rule(2, ['all'], 90), 'level' => Planner::LEVEL_GROUP],
            ['rule' => $rule, 'level' => Planner::LEVEL_MAILBOX],
        ]));
    }

    public function testTheWinnerBringsItsModeAndFlaggedMail(): void
    {
        $actions = Planner::actions([
            ['rule' => self::rule(1, ['folder:Alt'], 30, ['mode' => 'delete', 'keepFlagged' => 1]), 'level' => Planner::LEVEL_DOMAIN],
        ]);
        $this->assertSame([['folder' => 'folder:Alt', 'match' => 'folder', 'days' => 30, 'keep_flagged' => 1, 'mode' => 'delete', 'rule_id' => 1,
            'rule_name' => 'rule 1']], $actions);
        $this->assertSame([], Planner::actions([]));
    }

    public function testDescribeJoinsTheSubfoldersOfTheSameRule(): void
    {
        $described = fn(array $candidates): array => array_map(fn(array $action): string => $action['folder'] . '/' . $action['match']
            . ($action['subfolders'] ? '+' : '') . ':' . $action['rule_id'], Planner::describe(Planner::actions($candidates)));
        $trashAndJunk = ['rule' => self::rule(1, ['trash', 'junk'], 30), 'level' => Planner::LEVEL_GROUP];
        $junk = ['rule' => self::rule(2, ['junk'], 90), 'level' => Planner::LEVEL_GROUP];

        $this->assertSame(['junk/folder+:2', 'trash/folder+:1'], $described([$trashAndJunk, $junk]));
        // a name with * between them may take subfolders: they stay apart
        $this->assertSame(['junk/folder:2', 'trash/folder:1', 'folder:Junk/*/pattern:3', 'junk/subfolders+:2', 'trash/subfolders+:1'],
            $described([$trashAndJunk, $junk, ['rule' => self::rule(3, ['folder:Junk/*'], 60), 'level' => Planner::LEVEL_GROUP]]));
    }
}
