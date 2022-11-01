<?php

namespace Gazelle\Notification;

class Torrent extends \Gazelle\Base {
    protected $cond;
    protected $args;

    public function __construct(
        protected readonly int $userId,
    ) {
        $this->cond = ['unt.UserID = ?'];
        $this->args = [$userId];
    }

    protected function flush(): void {
        self::$cache->delete_value('user_notify_upload_' . $this->userId);
    }

    public function setFilter(int $filterId) {
        $cond[] = 'unf.ID = ?';
        $args[] = $filterId;
        return $this;
    }

    public function total(): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM users_notify_torrents AS unt
            INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
            LEFT JOIN users_notify_filters AS unf ON (unf.ID = unt.FilterID)
            WHERE " . implode(' AND ', $this->cond)
            , ...$this->args
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
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        self::$db->prepared_query('
            UPDATE users_notify_torrents SET
                UnRead = ?
            WHERE UserID = ?
            ', 0, $this->userId
        );
        $this->flush();
        return $list;
    }

    public function catchup(): int {
        self::$db->prepared_query("
            UPDATE users_notify_torrents SET
                UnRead = 0
            WHERE UnRead = 1 AND UserID = ?
            ", $this->userId
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
            ", $this->userId, $filterId
        );
        if (self::$db->affected_rows()) {
            $this->flush();
        }
        return self::$db->affected_rows();
    }

    public function clearFilter(int $filterId): int {
        self::$db->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = 0 AND UserID = ? AND FilterID = ?
            ", $this->userId, $filterId
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function clearRead(): int {
        self::$db->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = 0 AND UserID = ?
            ", $this->userId
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
            ", $this->userId, ...$torrentIds
        );
        $this->flush();
        return self::$db->affected_rows();
    }
}
