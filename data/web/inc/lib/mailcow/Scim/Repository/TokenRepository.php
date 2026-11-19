<?php

declare(strict_types=1);

namespace Mailcow\Scim\Repository;

use Mailcow\Core\Repository;
use Mailcow\Scim\Model\Token;
use PDO;

/**
 * SQL for the SCIM tokens (table `scim_tokens`).
 */
class TokenRepository extends Repository
{
    /** @return Token[] */
    public function all(): array
    {
        return array_map([Token::class, 'fromRow'],
            $this->query("SELECT * FROM `scim_tokens` ORDER BY `id`")->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?Token
    {
        $row = $this->query("SELECT * FROM `scim_tokens` WHERE `id` = :id", ['id' => $id])->fetch(PDO::FETCH_ASSOC);
        return $row ? Token::fromRow($row) : null;
    }

    public function findActiveByHash(string $hash): ?Token
    {
        $row = $this->query("SELECT * FROM `scim_tokens` WHERE `token_hash` = :hash AND `active` = 1", ['hash' => $hash])
            ->fetch(PDO::FETCH_ASSOC);
        return $row ? Token::fromRow($row) : null;
    }

    public function domainExists(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :domain", ['domain' => $domain])->fetchColumn();
    }

    public function insert(Token $token): int
    {
        $token->id = $this->insertRow('scim_tokens', $this->values($token) + ['token_hash' => $token->hash]);
        return $token->id;
    }

    public function update(Token $token): void
    {
        $this->updateRow('scim_tokens', (int)$token->id, $this->values($token));
    }

    public function delete(int $id): void
    {
        $this->query("DELETE FROM `scim_tokens` WHERE `id` = :id", ['id' => $id]);
    }

    private function values(Token $token): array
    {
        return [
            'description' => $token->description,
            'domain_restriction' => $token->domainRestriction,
            'group_domain' => $token->groupDomain,
            'allow_from' => implode(',', $token->allowFrom),
            'default_template' => $token->defaultTemplate,
            'mappers' => $token->mappers ? json_encode($token->mappers) : null,
            'templates' => $token->templates ? json_encode($token->templates) : null,
            'allow_rename' => $token->allowRename,
            'rename_alias' => $token->renameAlias,
            'active' => $token->active,
        ];
    }
}
