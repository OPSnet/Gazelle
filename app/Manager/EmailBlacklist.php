<?php

namespace Gazelle\Manager;

class EmailBlacklist extends \Gazelle\Base {
    protected $filterComment;
    protected $filterEmail;

    public function create(array $info): int {
        self::$db->prepared_query("
            INSERT INTO email_blacklist
                   (Email, Comment, UserID)
            VALUES (?,     ?,       ?)
            ", $info['email'], $info['comment'], $info['user_id']
        );
        return self::$db->inserted_id();
    }

    public function modify(int $id, array $info): bool {
        self::$db->prepared_query("
            UPDATE email_blacklist SET
                Email   = ?,
                Comment = ?,
                UserID  = ?,
                Time    = now()
            WHERE ID = ?
            ", $info['email'], $info['comment'], $info['user_id'], $id
        );
        return self::$db->affected_rows() === 1;
    }

    public function remove(int $id): bool {
        self::$db->prepared_query("
            DELETE FROM email_blacklist WHERE ID = ?
            ", $id
        );
        return self::$db->affected_rows() === 1;
    }

    public function filterComment(string $filterComment) {
        $this->filterComment = $filterComment;
        return $this;
    }

    public function filterEmail(string $filterEmail) {
        $this->filterEmail = $filterEmail;
        return $this;
    }

    public function queryBase(): array {
        $args = [];
        $cond = [];
        if ($this->filterComment) {
            $args[] = $this->filterComment;
            $cond[] = "Comment REGEXP ?";
        }
        if ($this->filterEmail) {
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
        return self::$db->scalar("SELECT count(*) $from", ...$args);
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
