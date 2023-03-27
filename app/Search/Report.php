<?php

namespace Gazelle\Search;

class Report extends \Gazelle\Base {
    protected array $args = [];
    protected array $cond = [];

    public function setId(int $id): Report {
        $this->cond[] = 'r.ID = ?';
        $this->args[]  = $id;
        return $this;
    }

    public function setStatus(string $status): Report {
        $this->cond[] = 'r.Status = ?';
        $this->args[]  = $status;
        return $this;
    }

    public function restrictForumMod(): Report {
        $this->cond[] = "r.Type IN ('comment', 'post', 'thread')";
        return $this;
    }

    public function total(): int {
        $cond = implode(' AND ', $this->cond);
        $where = $cond ? "WHERE $cond" : '';

        return (int)self::$db->scalar("
            SELECT count(*) FROM reports r $where
            ", ...$this->args
        );
    }

    public function page(int $limit, int $offset): array {
        $cond = implode(' AND ', $this->cond);
        $where = $cond ? "WHERE $cond" : '';

        self::$db->prepared_query("
            SELECT r.ID
            FROM reports r
            $where
            ORDER BY r.ReportedTime DESC
            LIMIT ? OFFSET ?
            ", ...[...$this->args, $limit, $offset]
        );
        return self::$db->collect(0, false);
    }
}
