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
}
