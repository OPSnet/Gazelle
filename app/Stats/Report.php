<?php

namespace Gazelle\Stats;

class Report extends \Gazelle\Base {

    public function day(int $day): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reports AS r
            WHERE r.ResolvedTime > now() - INTERVAL ? DAY
            GROUP BY r.ResolverID
            ORDER BY total DESC
            ", $day
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function allTime(): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reports AS r
            GROUP BY r.ResolverID
            ORDER BY total DESC
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function forumThreadTrashTotal(): array {
        self::$db->prepared_query("
            SELECT f.LastPostAuthorID AS user_id,
                count(*)              AS total
            FROM forums_topics AS f
            WHERE f.ForumID = ?
            GROUP BY f.LastPostAuthorID
            ORDER BY total DESC
            LIMIT 30
            ", TRASH_FORUM_ID
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function torrentOpenTotal(): int {
        $count = self::$cache->get_value('num_torrent_reportsv2');
        if ($count === false) {
            $count = self::$db->scalar("
                SELECT count(*) FROM reportsv2 WHERE Status = 'New'
            ");
            self::$cache->cache_value('num_torrent_reportsv2', $count, 3600);
        }
        return $count;
    }

    public function otherOpenTotal(): int {
        $count = self::$cache->get_value('num_other_reports');
        if ($count === false) {
            $count = self::$db->scalar("
                SELECT count(*) FROM reports WHERE Status = 'New'
            ");
            self::$cache->cache_value('num_other_reports', $count, 3600);
        }
        return $count;
    }

    public function forumOpenTotal(): int {
        $count = self::$cache->get_value('num_forum_reports');
        if ($count === false) {
            $count = self::$db->scalar("
                SELECT count(*)
                FROM reports
                WHERE Status = 'New'
                    AND Type IN ('artist_comment', 'collages_comment', 'post', 'requests_comment', 'thread', 'torrents_comment')
            ");
            self::$cache->cache_value('num_forum_reports', $count, 3600);
        }
        return $count;
    }
}
