<?php

namespace Gazelle\Manager;

class Registration extends \Gazelle\Base {

    protected $beforeDate;
    protected $afterDate;

    public function setBeforeDate(string $date) {
        $this->beforeDate = $date;
        return $this;
    }

    public function setAfterDate(string $date) {
        $this->afterDate = $date;
        return $this;
    }

    public function configure(): array {
        $cond = [];
        $args = [];
        if ($this->beforeDate) {
            if ($this->afterDate) {
                $cond[] = 'ui.JoinDate BETWEEN ? AND ?';
                $args[] = $this->afterDate;
                $args[] = $this->beforeDate;
            } else {
                $cond[] = 'ui.JoinDate < ?';
                $args[] = $this->beforeDate;
            }
        } elseif ($this->afterDate) {
            $cond[] = 'ui.JoinDate >= ?';
            $args[] = $this->afterDate;
        } else {
            $cond[] = 'ui.JoinDate > now() - INTERVAL 3 DAY';
        }
        return [implode(' AND ', $cond), $args];
    }

    public function total(): int {
        [$where, $args] = $this->configure();
        return self::$db->scalar("
            SELECT count(*) FROM users_info ui WHERE $where
            ", ...$args
        );
    }

    public function page(int $limit, int $offset): array {
        [$where, $args] = $this->configure();
        self::$db->prepared_query("
            SELECT ui.UserID FROM users_info AS ui
            WHERE $where
            ORDER BY ui.Joindate DESC
            LIMIT ? OFFSET ?
            ", ...array_merge($args, [$limit, $offset])
        );
        return self::$db->collect('UserID');
    }
}
