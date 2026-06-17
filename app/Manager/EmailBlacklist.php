<?php

namespace Gazelle\Manager;

use Gazelle\User;

/**
 * The name of this class (and the underlying table name) are ambiguous.
 * Individual email addresses as well as email domains can be blacklisted.
 */

class EmailBlacklist extends \Gazelle\Base {
    protected string $filterComment;
    protected string $filterEmail;

    public function create(string $email, string $comment, User $user): int {
        return $this->pg()->scalarex("
            insert into email_blacklist
                   (email, comment, id_user)
            values ($1,    $2,      $3)
            returning id_email_blacklist
            ", $email, $comment, $user->id
        );
    }

    public function modify(int $id, string $email, string $comment, User $user): int {
        return $this->pg()->query("
            update email_blacklist set
                email   = $1,
                comment = $2,
                id_user = $3
            where id_email_blacklist = $4
            ", $email, $comment, $user->id, $id
        );
    }

    public function remove(int $id): int {
        return $this->pg()->query("
            delete from email_blacklist where id_email_blacklist = $1
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
        if (isset($this->filterComment)) {
            $cond[] = "comment ~ {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filterComment;
        }
        if (isset($this->filterEmail)) {
            $cond[] = "email ~ {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filterEmail;
        }
        return [
            "from email_blacklist" . ($cond === [] ? '' : (' where ' . implode(' and ', $cond))),
            $args
        ];
    }

    public function total(): int {
        [$from, $args] = $this->queryBase();
        return (int)$this->pg()->scalarex("select count(*) $from", ...$args);
    }

    public function page(int $limit, int $offset): array {
        [$from, $args] = $this->queryBase();
        $limitPh = "limit {$this->pg()->placeholder(count($args))}";
        $args[] = $limit;
        $offsetPh = "offset {$this->pg()->placeholder(count($args))}";
        $args[] = $offset;
        return $this->pg()->allex("
            select id_email_blacklist as id,
                id_user,
                created,
                email,
                comment
            $from
            order by created desc
            $limitPh $offsetPh
            ", ...$args
        );
    }
}
