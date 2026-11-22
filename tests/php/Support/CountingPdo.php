<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

use PDO;
use PDOStatement;

// PDO that counts the domain lookups of bare_localpart_login_qualify()
final class CountingPdo extends PDO
{
    public int $domainLookups = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FROM `domain` WHERE `domain` = :d')) {
            $this->domainLookups++;
        }
        return parent::prepare($query, $options);
    }
}
