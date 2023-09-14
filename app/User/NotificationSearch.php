<?php

namespace Gazelle\User;

class NotificationSearch extends \Gazelle\BaseUser {
    final const tableName = 'users_notify_torrents';

    protected bool $dirty = true;
    protected int $filterId;
    protected string $baseQuery;
    protected array $cond = [];
    protected array $args = [];

    public function flush(): static { $this->user()->flush(); return $this; }

    public function __construct(
        \Gazelle\User $user,
        protected string $orderBy,
        protected string $direction,
        protected \Gazelle\Manager\Torrent $torMan,
    ) {
        parent::__construct($user);
    }

    protected function configure(): void {
        if ($this->dirty) {
            $this->dirty = false;
            $this->baseQuery = "
                FROM users_notify_torrents AS unt
                INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
                INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = unt.TorrentID)
            ";
            $this->cond = ['unt.UserID = ?'];
            $this->args = [$this->user->id()];

            if ($this->orderBy == 'tg.Year') {
                $this->baseQuery .= " INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)";
            }
            $this->baseQuery .= 'WHERE ' . implode(' AND ', $this->cond);
        }
    }

    public function setFilter(int $filterId): static {
        $this->filterId = $filterId;
        $this->cond[] = 'unt.FilterID = ?';
        $this->args[] = $filterId;
        return $this;
    }

    public function filterId(): ?int {
        return $this->filterId ?? null;
    }

    public function pageSql(): string {
        $this->configure();
        return "
            SELECT unt.TorrentID,
                unt.UnRead        AS unread,
                unt.FilterID      AS filter_id
                {$this->baseQuery}
                ORDER BY {$this->orderBy} {$this->direction}
                LIMIT ? OFFSET ?
        ";
    }

    public function page(int $limit, int $offset): array {
        self::$db->prepared_query($this->pageSql(), ...[...$this->args, $limit, $offset]);
        $list = [];
        foreach (self::$db->to_array(false, MYSQLI_ASSOC, false) as $row) {
            $list[] = [...$row, 'torrent' => $this->torMan->findById($row['TorrentID'])];
        }
        return $list;
    }

    public function totalSql(): string {
        $this->configure();
        return "SELECT count(*) " . $this->baseQuery;
    }

    public function total(): int {
        return (int)self::$db->scalar($this->totalSql(), ...$this->args);
    }

    public function clearUnread(array $torrentIdList): int {
        self::$db->prepared_query("
            UPDATE users_notify_torrents SET
                UnRead = 0
            WHERE UserID = ?
                AND TorrentID IN (" . placeholders($torrentIdList) . ")
            ", $this->user->id(), ...$torrentIdList
        );
        self::$cache->delete_value('user_notify_upload_' . $this->user->id());
        return self::$db->affected_rows();
    }
}
