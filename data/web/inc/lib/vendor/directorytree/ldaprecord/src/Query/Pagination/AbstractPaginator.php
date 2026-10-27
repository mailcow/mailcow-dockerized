<?php

namespace LdapRecord\Query\Pagination;

use LdapRecord\LdapInterface;
use LdapRecord\Query\Builder;

abstract class AbstractPaginator
{
    /**
     * The query builder instance.
     */
    protected Builder $query;

    /**
     * The filter to execute.
     */
    protected string $filter;

    /**
     * The amount of objects to fetch per page.
     */
    protected int $perPage;

    /**
     * Whether the operation is critical.
     */
    protected bool $isCritical;

    /**
     * Constructor.
     */
    public function __construct(Builder $query, string $filter, int $perPage, bool $isCritical = false)
    {
        $this->query = $query;
        $this->filter = $filter;
        $this->perPage = $perPage;
        $this->isCritical = $isCritical;
    }

    /**
     * Execute the pagination request.
     */
    public function execute(LdapInterface $ldap): mixed
    {
        $pages = [];

        $this->prepareServerControls();

        do {
            $this->applyServerControls($ldap);

            if (! $resource = $this->query->run($this->filter)) {
                break;
            }

            $this->updateServerControls($ldap, $resource);

            $pages[] = $this->query->parse($resource);
        } while ($this->shouldContinue());

        $this->resetServerControls($ldap);

        return $pages;
    }

    /**
     * Whether the paginator should continue iterating.
     */
    protected function shouldContinue(): bool
    {
        $cookie = $this->fetchCookie();

        return $cookie !== '';
    }

    /**
     * Fetch the pagination cookie.
     */
    abstract protected function fetchCookie(): ?string;

    /**
     * Prepare the server controls before executing the pagination request.
     */
    abstract protected function prepareServerControls(): void;

    /**
     * Apply the server controls.
     */
    abstract protected function applyServerControls(LdapInterface $ldap): void;

    /**
     * Reset the server controls.
     */
    abstract protected function resetServerControls(LdapInterface $ldap): void;

    /**
     * Update the server controls.
     */
    abstract protected function updateServerControls(LdapInterface $ldap, mixed $resource): void;
}
