<?php

namespace LdapRecord\Query\Pagination;

use Generator;
use LdapRecord\LdapInterface;

class LazyPaginator extends Paginator
{
    /**
     * Execute the pagination request.
     */
    public function execute(LdapInterface $ldap): Generator
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
