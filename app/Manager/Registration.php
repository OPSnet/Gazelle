<?php

namespace Gazelle\Manager;

// TODO: This should be subsumed into Search\User

class Registration extends \Gazelle\Base {
    protected string $beforeDate;
    protected string $afterDate;

    public function setBeforeDate(string $date): static {
        $this->beforeDate = $date;
        return $this;
    }

    public function setAfterDate(string $date): static {
        $this->afterDate = $date;
        return $this;
    }

    public function configure(): array {
        $cond = [];
        $args = [];
        if (isset($this->beforeDate)) {
            if (isset($this->afterDate)) {
                $cond[] = 'um.created BETWEEN ? AND ?';
                $args[] = $this->afterDate;
                $args[] = $this->beforeDate;
            } else {
                $cond[] = 'um.created < ?';
                $args[] = $this->beforeDate;
            }
        } elseif (isset($this->afterDate)) {
            $cond[] = 'um.created >= ?';
            $args[] = $this->afterDate;
        } else {
            $cond[] = 'um.created > now() - INTERVAL 3 DAY';
        }
        return [implode(' AND ', $cond), $args];
    }

    public function total(): int {
        [$where, $args] = $this->configure();
        return (int)self::$db->scalar("
            SELECT count(*) FROM users_main um WHERE $where
            ", ...$args
        );
    }

    public function page(int $limit, int $offset): array {
        [$where, $args] = $this->configure();
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main um
            WHERE $where
            ORDER BY um.created DESC
            LIMIT ? OFFSET ?
            ", ...array_merge($args, [$limit, $offset])
        );
        return self::$db->collect(0, false);
    }
}
