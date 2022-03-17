<?php

namespace LdapRecord\Query\Pagination;

use LdapRecord\LdapInterface;

class LazyPaginator extends Paginator
{
    /**
     * Execute the pagination request.
     *
     * @param LdapInterface $ldap
     *
     * @return \Generator
     */
    public function execute(LdapInterface $ldap)
    {
        $this->prepareServerControls();

        do {
            $this->applyServerControls($ldap);

            if (! $resource = $this->query->run($this->filter)) {
                break;
            }

            $this->updateServerControls($ldap, $resource);

            yield $this->query->parse($resource);
        } while ($this->shouldContinue());

        $this->resetServerControls($ldap);
    }
}
