<?php

namespace Gazelle\User\Notification;

class Torrent extends AbstractNotification {
    public function className(): string {
        return 'confirmation';
    }

    public function clear(): int {
        self::$db->prepared_query("
            UPDATE users_notify_torrents SET
                Unread = 0
            WHERE UnRead = 1
                AND UserID = ?
            ", $this->user->id()
        );
        self::$cache->delete_value('user_notify_upload_' . $this->user->id());
        return self::$db->affected_rows();
    }

    public function load(): bool {
        if ($this->user->permitted('site_torrents_notify')) {
            $total = $this->unread();
            if ($total > 0) {
                $this->title = 'You have ' . article($total) . ' new torrent notification' . plural($total);
                $this->url   = 'torrents.php?action=notify';
                return true;
            }
        }
        return false;
    }

    public function total(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM users_notify_torrents WHERE UserID = ?
            ", $this->user->id()
        );
    }

    public function unread(): int {
        $total = self::$cache->get_value('user_notify_upload_' . $this->user->id());
        if ($total === false) {
            $total = (int)self::$db->scalar("
                SELECT count(*)
                FROM users_notify_torrents
                WHERE UnRead = 1
                    AND UserID = ?
                ", $this->user->id()
            );
            self::$cache->cache_value('user_notify_upload_' . $this->user->id(), $total, 0);
        }
        return $total;
    }

    public function page(\Gazelle\Manager\Torrent $torMan, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT unt.TorrentID AS torrent_id,
                unt.UnRead       AS unread,
                unf.ID           AS filter_id,
                coalesce(unf.Label, 'Deleted filter')
                                 AS filter_name
            FROM users_notify_torrents unt
            LEFT JOIN users_notify_filters unf ON (unf.ID = unt.FilterID)
            WHERE unt.UserID = ?
            ORDER BY unt.TorrentID DESC
            LIMIT ? OFFSET ?
            ", $this->user->id(), $limit, $offset
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$item) {
            $torrent = $torMan->findById($item['torrent_id']);
            if ($torrent) {
                $item['torrent'] = $torrent;
            }
        }
        return $list;
    }
}
