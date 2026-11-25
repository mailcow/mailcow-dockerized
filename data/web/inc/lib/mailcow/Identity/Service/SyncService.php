<?php

declare(strict_types=1);

namespace Mailcow\Identity\Service;

use Closure;
use Mailcow\Core\UserError;
use Mailcow\Identity\Directory\Directory;
use Mailcow\Identity\Directory\DirectoryException;
use Mailcow\Identity\Legacy\LegacyAccounts;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Model\State;
use Mailcow\Identity\Model\SyncPlan;
use Mailcow\Identity\Repository\IdentityRepository;
use Mailcow\Identity\Repository\RunRepository;
use Mailcow\MailboxGroup\Service\IdpSyncService;
use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Sogo\Service\SogoService;
use Throwable;

/**
 * Sync runs of the IdP (cron only): list the directory, plan, apply. A listing error aborts the run before
 * anything is written; a blocked or preview run writes nothing but the run summary.
 * The directory is created only when a run is due, so idle cron calls never connect to the IdP.
 */
class SyncService
{
    /** A run still `running` after this many minutes crashed */
    private const STALE_MINUTES = 60;
    /** Errors kept in the run summary */
    private const DETAILS = 50;

    public function __construct(
        private Settings $settings,
        /** @var Closure(): ?Directory */
        private Closure $directory,
        private IdentityRepository $repo,
        private RunRepository $runs,
        private SyncPlanner $planner,
        private IdpSyncService $groups,
        private TemplateService $templates,
        private LegacyAccounts $legacy,
        private SogoService $sogo
    ) {
    }

    /**
     * Runs the oldest requested run, else a scheduled run when the sync interval is over.
     *
     * @return ?array the finished run (id, mode, status, summary), null if nothing was due
     */
    public function runDue(): ?array
    {
        $this->runs->failStale(self::STALE_MINUTES);
        $run = $this->runs->nextPending();
        if ($run === null) {
            if (!$this->settings->syncEnabled()) {
                return null;
            }
            $since = $this->runs->secondsSinceScheduled();
            if ($since !== null && $since < $this->settings->syncInterval * 60) {
                return null;
            }
            $run = ['id' => $this->runs->create($this->settings->provider, 'run'), 'mode' => 'run'];
        }
        return $this->execute((int)$run['id'], (string)$run['mode']);
    }

    /** @return array{id: int, mode: string, status: string, summary: array} */
    public function execute(int $id, string $mode): array
    {
        $this->runs->start($id);
        try {
            $directory = $this->settings->canSync() ? ($this->directory)() : null;
            if ($directory === null) {
                throw new DirectoryException('The identity provider cannot be synced');
            }
            $listing = $directory->listAll($this->settings->groupSync);
            $plan = $this->planner->plan($this->settings, $listing, $this->state(), $mode === 'force');
            $summary = $plan->summary();
            if ($mode === 'preview') {
                $status = 'ok';
            } elseif ($plan->blocked) {
                $status = 'blocked';
            } else {
                [$errors, $updated] = $this->apply($plan);
                $summary['counts']['updates'] = $updated;
                $summary['counts']['errors'] = count($errors);
                $summary['errors'] = array_slice($errors, 0, self::DETAILS);
                $status = 'ok';
            }
        } catch (DirectoryException $e) {
            $status = 'error';
            $summary = ['error' => $e->getMessage()];
        } catch (Throwable $e) {
            $this->runs->finish($id, 'error', ['error' => 'The sync run failed: ' . $e->getMessage(), 'mode' => $mode]);
            throw $e;
        }
        $summary['mode'] = $mode;
        $this->runs->finish($id, $status, $summary);
        return ['id' => $id, 'mode' => $mode, 'status' => $status, 'summary' => $summary];
    }

    private function state(): State
    {
        $accounts = $this->repo->accounts($this->settings->provider);
        return new State($accounts, $this->repo->mailboxes(), $this->repo->domains(),
            $this->settings->groupSync ? $this->groups->groups() : [],
            $this->settings->periodicSync ? $this->templates->effective(array_keys($accounts)) : [],
            $this->repo->templateHashes());
    }

    /** @return array{0: string[], 1: int} errors of single changes (the rest is still applied), updated mailboxes */
    private function apply(SyncPlan $plan): array
    {
        $provider = $this->settings->provider;
        $errors = [];
        foreach ($plan->links as $username => $externalId) {
            $this->repo->link((string)$username, $provider, $externalId);
        }
        foreach ($plan->found as $username) {
            $this->repo->setMissing($username, $provider, false);
        }
        foreach ($plan->missing as $username) {
            $this->repo->setMissing($username, $provider, true);
        }
        foreach ($plan->creates as $create) {
            $error = $this->legacy->createMailbox($create['email'], $create['name'], $provider, $create['template']);
            if ($error !== null) {
                $errors[] = $create['email'] . ': ' . $error;
                continue;
            }
            $this->repo->link($create['email'], $provider, $create['id']);
        }
        foreach ($plan->deactivate as $username) {
            $this->repo->setActive($username, $provider, 2, true);
        }
        foreach ($plan->restore as $username) {
            $this->repo->setActive($username, $provider, 1, false);
        }
        foreach ($plan->release as $username) {
            $this->repo->setIdpDisabled($username, $provider, false);
        }
        $this->sogo->refreshStaticView(array_merge($plan->deactivate, $plan->restore));
        $changed = [];
        $errors = array_merge($errors, $this->applyGroups($plan, $changed));
        [$updateErrors, $updated] = $this->applyUpdates($plan, $changed);
        return [array_merge($errors, $updateErrors), $updated];
    }

    /**
     * @param string[] $changed collects the members that joined or left a group
     * @return string[]
     */
    private function applyGroups(SyncPlan $plan, array &$changed): array
    {
        $errors = [];
        foreach ($plan->groupCreates as $create) {
            try {
                foreach ($this->groups->createFromIdp($create['ref'], $create['name'], $create['domain'], $create['mail']) as $warning) {
                    $errors[] = $create['name'] . ': ' . $warning[0];
                }
            } catch (UserError $e) {
                $errors[] = $create['name'] . ': ' . $e->key;
            }
        }
        foreach ($plan->groupRenames as $id => $name) {
            try {
                $this->groups->renameFromIdp((int)$id, $name);
            } catch (UserError $e) {
                $errors[] = $name . ': ' . $e->key;
            }
        }
        foreach ($plan->memberships as $ref => $usernames) {
            $group = $this->groups->findByIdpRef((string)$ref);
            if ($group !== null) {
                $this->groupResult($group->name, $this->groups->syncGroupMembers((int)$group->id, $usernames), $errors, $changed);
            }
        }
        foreach ($plan->orphanedGroups as $id) {
            $this->groupResult((string)$id, $this->groups->syncGroupMembers($id, []), $errors, $changed);
        }
        return $errors;
    }

    /**
     * Name and template updates after the group changes: planned updates and members that joined or left a group
     * in this run (e.g. the legacy mapping applies again after leaving a template group). The effective template
     * is resolved and compared again.
     *
     * @return array{0: string[], 1: int} errors, updated mailboxes
     */
    private function applyUpdates(SyncPlan $plan, array $changed): array
    {
        $candidates = array_values(array_unique(array_merge(array_column($plan->updates, 'username'),
            array_intersect($changed, array_keys($plan->profiles)))));
        if (empty($candidates)) {
            return [[], 0];
        }
        $errors = [];
        $updated = 0;
        $accounts = $this->repo->accounts($this->settings->provider);
        $hashes = $this->repo->templateHashes();
        $effective = $this->templates->effective($candidates);
        foreach ($candidates as $username) {
            $profile = $plan->profiles[$username] ?? null;
            $account = $accounts[$username] ?? null;
            $template = $effective[$username] ?? $profile['template'] ?? null;
            if ($profile === null || $account === null || $template === null
                || ($profile['name'] === $account->name && ($hashes[$template] ?? null) === $account->attributeHash)) {
                continue;
            }
            $error = $this->legacy->applyTemplate($username, $profile['name'], $template);
            if ($error !== null) {
                $errors[] = $username . ': ' . $error;
            } else {
                $updated++;
            }
        }
        return [$errors, $updated];
    }

    private function groupResult(string $group, array $result, array &$errors, array &$changed): void
    {
        foreach ($result['warnings'] as $warning) {
            $errors[] = $group . ': ' . $warning[0];
        }
        array_push($changed, ...$result['added'], ...$result['removed']);
    }
}
