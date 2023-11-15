<?php

namespace Gazelle\Manager;

/**
 * The name of this class (and the underlying table name) are ambiguous.
 * Individual email addresses as well as email domains can be blacklisted.
 */

class EmailBlacklist extends \Gazelle\Base {
    protected string $filterComment;
    protected string $filterEmail;

    public function create(string $domain, string $comment, \Gazelle\User $user): int {
        self::$db->prepared_query("
            INSERT INTO email_blacklist
                   (Email, Comment, UserID)
            VALUES (?,     ?,       ?)
            ", $domain, $comment, $user->id()
        );
        return self::$db->inserted_id();
    }

    public function modify(int $id, string $domain, string $comment, \Gazelle\User $user): int {
        self::$db->prepared_query("
            UPDATE email_blacklist SET
                Email   = ?,
                Comment = ?,
                UserID  = ?,
                Time    = now()
            WHERE ID = ?
            ", $domain, $comment, $user->id(), $id
        );
        return self::$db->affected_rows();
    }

    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM email_blacklist WHERE ID = ?
            ", $id
        );
        return self::$db->affected_rows();
    }

    public function exists(string $target): bool {
        // This is a bit fragile: if someone adds an incorrect regexp, it will abort
        return (bool)self::$db->scalar("
            select 1 from email_blacklist WHERE ? REGEXP (
                SELECT group_concat(Email SEPARATOR '|') FROM email_blacklist
            )
            LIMIT 1
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
            $cond[] = "Comment REGEXP ?";
        }
        if (!empty($this->filterEmail)) {
            $args[] = $this->filterEmail;
            $cond[] = "Email REGEXP ?";
        }
        return [
            "FROM email_blacklist" . (empty($cond) ? '' : (' WHERE ' . implode(' AND ', $cond))),
            $args
        ];
    }

    public function total(): int {
        [$from, $args] = $this->queryBase();
        return (int)self::$db->scalar("SELECT count(*) $from", ...$args);
    }

    public function page(int $limit, int $offset): array {
        [$from, $args] = $this->queryBase();
        $args = array_merge($args, [$limit, $offset]);
        self::$db->prepared_query("
            SELECT ID   AS id,
                UserID  AS user_id,
                Time    AS time,
                Email   AS email,
                Comment AS comment
            $from
            ORDER BY Time DESC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
