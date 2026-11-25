<?php

declare(strict_types=1);

namespace Mailcow\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Base class of the repositories: the PDO plus the helpers every repository needs.
 * Named parameters are passed without the colon (['id' => 5]).
 */
class Repository
{
    public function __construct(protected PDO $pdo)
    {
    }

    /** Prepares and executes one statement */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Inserts one row (column => value) and returns the new id */
    protected function insertRow(string $table, array $values): int
    {
        $columns = array_keys($values);
        $this->query("INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (:" . implode(', :', $columns) . ")", $values);
        return (int)$this->pdo->lastInsertId();
    }

    /** Updates the columns (column => value) of the row with the given `id` */
    protected function updateRow(string $table, int $id, array $values): void
    {
        $set = implode(', ', array_map(fn(string $column): string => "`$column` = :$column", array_keys($values)));
        $this->query("UPDATE `$table` SET $set WHERE `id` = :id", $values + ['id' => $id]);
    }

    /**
     * Runs $work in a transaction and returns its result. Inside a transaction of the caller it just runs,
     * so the caller keeps control over commit and rollback.
     */
    protected function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
