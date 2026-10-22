<?php

namespace Gazelle\Notification;

class Torrent extends \Gazelle\Base {
    protected array $cond;
    protected array $args;

    public function __construct(
        protected readonly \Gazelle\User $user,
    ) {
        $this->cond = ['unt.UserID = ?'];
        $this->args = [$user->id];
    }

    public function flush(): static {
        self::$cache->delete_value('user_notify_upload_' . $this->user->id);
        return $this;
    }

    public function setFilter(int $filterId): static {
        $this->cond[] = 'unf.ID = ?';
        $this->args[] = $filterId;
        return $this;
    }

    public function total(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM users_notify_torrents AS unt
            INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
            LEFT JOIN users_notify_filters AS unf ON (unf.ID = unt.FilterID)
            WHERE " . implode(' AND ', $this->cond),
            ...$this->args
        );
    }

    public function unreadList(int $limit, int $offset): array {
        $args = array_merge($this->args, [$limit, $offset]);
        self::$db->prepared_query("
            SELECT t.GroupID  AS groupId,
                unt.UnRead    AS unread,
                unf.Label     AS label,
                unt.TorrentID AS torrentId
            FROM users_notify_torrents AS unt
            INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
            LEFT JOIN users_notify_filters AS unf ON (unf.ID = unt.FilterID)
            WHERE " . implode(' AND ', $this->cond) . "
            ORDER BY unf.Label, unt.TorrentID DESC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC);
        self::$db->prepared_query('
            UPDATE users_notify_torrents SET
                UnRead = ?
            WHERE UserID = ?
            ', 0, $this->user->id
        );
        $this->flush();
        return $list;
    }

    public function catchup(): int {
        self::$db->prepared_query("
            UPDATE users_notify_torrents SET
                UnRead = 0
            WHERE UnRead = 1 AND UserID = ?
            ", $this->user->id
        );
        if (self::$db->affected_rows()) {
            $this->flush();
        }
        return self::$db->affected_rows();
    }

    public function catchupFilter(int $filterId): int {
        self::$db->prepared_query("
            UPDATE users_notify_torrents SET
                UnRead = 0
            WHERE UnRead = 1 AND UserID = ? AND FilterID = ?
            ", $this->user->id, $filterId
        );
        if (self::$db->affected_rows()) {
            $this->flush();
        }
        return self::$db->affected_rows();
    }

    public function clearFilter(int $filterId): int {
        self::$db->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = 0 AND UserID = ? AND FilterID = ?
            ", $this->user->id, $filterId
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function clearRead(): int {
        self::$db->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = 0 AND UserID = ?
            ", $this->user->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function clearTorrentList(array $torrentIds): int {
        if (!$torrentIds) {
            return 0;
        }
        self::$db->prepared_query("
            DELETE FROM users_notify_torrents WHERE UserID = ? AND TorrentID IN (" . placeholders($torrentIds) . ")
            ", $this->user->id, ...$torrentIds
        );
        $this->flush();
        return self::$db->affected_rows();
    }
}
