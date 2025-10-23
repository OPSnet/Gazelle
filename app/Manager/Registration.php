<?php

namespace Gazelle\Manager;

// TODO: This should be subsumed into Search\User

class Registration extends \Gazelle\Base {
    protected string $beforeDate;
    protected string $afterDate;
    protected int $source;

    public function __construct(
        protected User $manager = new User(),
    ) {}

    public function setBeforeDate(string $date): static {
        $this->beforeDate = $date;
        return $this;
    }

    public function setAfterDate(string $date): static {
        $this->afterDate = $date;
        return $this;
    }

    public function setSource(int $sourceId): static {
        $this->source = $sourceId;
        return $this;
    }

    public function configure(): array {
        $cond = [];
        $args = [];
        $join = '';
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
        if (isset($this->source)) {
            $cond[] = 'uhis.invite_source_id = ?';
            $args[] = $this->source;
            $join = 'INNER JOIN user_has_invite_source uhis ON (uhis.user_id = um.ID)';
        }
        return [$join, implode(' AND ', $cond), $args];
    }

    public function total(): int {
        [$join, $where, $args] = $this->configure();
        return (int)self::$db->scalar("
            SELECT count(*) FROM users_main um $join WHERE $where
            ", ...$args
        );
    }

    public function page(int $limit, int $offset): array {
        [$join, $where, $args] = $this->configure();
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main um
            $join
            WHERE $where
            ORDER BY um.created DESC
            LIMIT ? OFFSET ?
            ", ...array_merge($args, [$limit, $offset])
        );
        $list = [];
        foreach (self::$db->collect(0) as $userId) {
            $user = $this->manager->findById($userId);
            if ($user) {
                $list[] = $user;
            }
        }
        return $list;
    }
}
