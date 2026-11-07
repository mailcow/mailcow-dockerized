<?php

declare(strict_types=1);

namespace Mailcow\Retention\Service;

use Mailcow\Retention\Model\Rule;

/**
 * What the rules of one mailbox delete: the most specific rule wins, per folder of the mailbox. The folders of the
 * rules become entries in order: a rule assigned to the mailbox before one of its groups before one of the whole
 * domain; then by what an entry matches (MATCHES, the folder itself first); then the longer period, then the lower
 * id. The runner gives every folder to the first entry that matches it.
 */
class Planner
{
    public const LEVEL_DOMAIN = 1;
    public const LEVEL_GROUP = 2;
    public const LEVEL_MAILBOX = 3;

    /**
     * What an entry matches, most specific first: the folder itself (a special folder, the inbox, an own folder
     * without *), an own folder name with *, the subfolders of a special folder or the inbox, all folders
     */
    public const MATCHES = ['folder' => 3, 'pattern' => 2, 'subfolders' => 1, 'all' => 0];

    /**
     * @param array<array{rule: Rule, level: int}> $candidates active rules of one mailbox with their assignment level
     * @return array[] in order: folder (key), match, days, keep_flagged, mode, rule_id, rule_name. Entries that never
     *                 win are left out: one matching the same folders as an entry before and everything after all folders.
     */
    public static function actions(array $candidates): array
    {
        $entries = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate['rule']->folders as $folder) {
                foreach (self::matches($folder) as $match) {
                    // the lower id decides between equal rules, so the result does not depend on the order
                    $entries[] = [
                        'score' => [$candidate['level'], self::MATCHES[$match], $candidate['rule']->days, -(int)$candidate['rule']->id],
                        'folder' => $folder,
                        'match' => $match,
                        'rule' => $candidate['rule'],
                    ];
                }
            }
        }
        usort($entries, fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $actions = [];
        foreach ($entries as $entry) {
            if (isset($actions[$entry['folder'] . "\n" . $entry['match']])) {
                continue;
            }
            $actions[$entry['folder'] . "\n" . $entry['match']] = self::action($entry['folder'], $entry['match'], $entry['rule']);
            if ($entry['match'] === 'all') {
                break;
            }
        }
        return array_values($actions);
    }

    /**
     * The plan for people: the subfolders of a special folder or the inbox go with the folder (subfolders 1) when the
     * same rule decides both and no entry with * comes between them.
     *
     * @return array[] the entries of actions() with subfolders 0 or 1
     */
    public static function describe(array $actions): array
    {
        $described = [];
        $folders = [];
        foreach ($actions as $action) {
            $key = $action['folder'] . "\n" . $action['rule_id'];
            if ($action['match'] === 'subfolders' && isset($folders[$key])) {
                $described[$folders[$key]]['subfolders'] = 1;
                continue;
            }
            if ($action['match'] === 'pattern') {
                $folders = [];
            } elseif ($action['match'] === 'folder') {
                $folders[$key] = count($described);
            }
            $described[] = $action + ['subfolders' => (int)($action['match'] === 'subfolders')];
        }
        return $described;
    }

    /** @return string[] what the folder key of a rule matches */
    private static function matches(string $folder): array
    {
        if ($folder === 'all') {
            return ['all'];
        }
        if (str_starts_with($folder, Rule::OWN_FOLDER)) {
            return [str_contains($folder, '*') ? 'pattern' : 'folder'];
        }
        return ['folder', 'subfolders'];
    }

    private static function action(string $folder, string $match, Rule $rule): array
    {
        return [
            'folder' => $folder,
            'match' => $match,
            'days' => $rule->days,
            'keep_flagged' => $rule->keepFlagged,
            'mode' => $rule->mode,
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
        ];
    }
}
