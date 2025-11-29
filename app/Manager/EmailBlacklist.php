<?php

namespace Gazelle\Manager;

/**
 * The name of this class (and the underlying table name) are ambiguous.
 * Individual email addresses as well as email domains can be blacklisted.
 */

class EmailBlacklist extends \Gazelle\Base {
    protected string $filterComment;
    protected string $filterEmail;

    public function create(string $email, string $comment, \Gazelle\User $user): int {
        return $this->pg()->insert("
            insert into email_blacklist
                   (email, comment, id_user)
            VALUES (?,     ?,       ?)
            ", $email, $comment, $user->id
        );
    }

    public function modify(int $id, string $email, string $comment, \Gazelle\User $user): int {
        return $this->pg()->prepared_query("
            update email_blacklist set
                email   = ?,
                comment = ?,
                id_user = ?
            where id_email_blacklist = ?
            ", $email, $comment, $user->id, $id
        );
    }

    public function remove(int $id): int {
        return $this->pg()->prepared_query("
            delete from email_blacklist where id_email_blacklist = ?
            ", $id
        );
    }

    public function exists(string $target): bool {
        return (bool)$this->pg()->scalarex("
            select 1
            from email_blacklist
            where $1 ~* email
            ", $target
        );
    }

    public function setFilterComment(string $filterComment): static {
        $this->filterComment = $filterComment;
        return $this;
    }

    public function setFilterEmail(string $filterEmail): static {
        $this->filterEmail = $filterEmail;
        return $this;
    }

    public function queryBase(): array {
        $args = [];
        $cond = [];
        if (!empty($this->filterComment)) {
            $args[] = $this->filterComment;
            $cond[] = "comment ~ ?";
        }
        if (!empty($this->filterEmail)) {
            $args[] = $this->filterEmail;
            $cond[] = "email ~ ?";
        }
        return [
            "from email_blacklist" . (empty($cond) ? '' : (' where ' . implode(' and ', $cond))),
            $args
        ];
    }

    public function total(): int {
        [$from, $args] = $this->queryBase();
        return (int)$this->pg()->scalar("select count(*) $from", ...$args);
    }

    public function page(int $limit, int $offset): array {
        [$from, $args] = $this->queryBase();
        $args = array_merge($args, [$limit, $offset]);
        return $this->pg()->all("
            select id_email_blacklist as id,
                id_user,
                created,
                email,
                comment
            $from
            order by created desc
            limit ? offset ?
            ", ...$args
        );
    }
}
