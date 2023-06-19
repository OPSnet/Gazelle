<?php

namespace Gazelle\Stats;

class TGroups extends \Gazelle\Base {
    public function refresh(): int {
        self::$db->dropTemporaryTable("tgroup_summary_new");
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tgroup_summary_new LIKE tgroup_summary
        ");

        /* Need to perform dirty reads to avoid wedging users, especially inserts to users_downloads */
        self::$db->prepared_query("
            SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED
        ");

        self::$db->prepared_query("
            INSERT INTO tgroup_summary_new (tgroup_id, leech_total, seeding_total)
                SELECT tg.ID,
                    sum(CASE WHEN xfu.remaining > 0 THEN 1 ELSE 0 END) AS leech_total,
                    sum(CASE WHEN xfu.remaining = 0 THEN 1 ELSE 0 END) AS seed_total
                FROM xbt_files_users AS xfu
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                GROUP BY tg.ID
        ");

        self::$db->prepared_query("
            INSERT INTO tgroup_summary_new (tgroup_id, download_total)
                SELECT tg.ID,
                    count(*) AS total
                FROM users_downloads AS ud
                INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                GROUP BY tg.ID
            ON DUPLICATE KEY UPDATE download_total = VALUES(download_total)
        ");

        self::$db->prepared_query("
            INSERT INTO tgroup_summary_new (tgroup_id, snatch_total)
                SELECT tg.ID,
                    count(*) AS total
                FROM xbt_snatched AS xs
                INNER JOIN torrents AS t ON (t.ID = xs.fid)
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                GROUP BY tg.ID
            ON DUPLICATE KEY UPDATE snatch_total = VALUES(snatch_total)
        ");

        self::$db->prepared_query("
            INSERT INTO tgroup_summary_new (tgroup_id, bookmark_total)
                SELECT b.GroupID, count(*) as bookmark_total
                FROM bookmarks_torrents b
                INNER JOIN torrents_group AS tg ON (tg.ID = b.GroupID)
                GROUP BY b.GroupID
            ON DUPLICATE KEY UPDATE bookmark_total = VALUES(bookmark_total)
        ");

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM tgroup_summary
        ");
        // For performance reasons related to locking and deadlocks, the various tables
        // are read uncommitted. This means that between the beginning of the function
        // and now, a torrents_group row may have been deleted following a merge.
        // We therefore must select only the rows that still exist when refreshing
        // the tgroup_summary table.
        self::$db->prepared_query("
            INSERT INTO tgroup_summary
            SELECT *
            FROM tgroup_summary_new tsn
            WHERE EXISTS (SELECT 1 FROM torrents_group tg WHERE tg.ID = tsn.tgroup_id)
        ");
        $processed = self::$db->affected_rows();
        self::$db->commit();
        self::$db->dropTemporaryTable("tgroup_summary_new");
        return $processed;
    }
}
