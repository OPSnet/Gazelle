<?php

namespace Gazelle\Stats;

class Artists extends \Gazelle\Base {
    public function updateUsage(): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM artist_usage
        ");
        self::$db->prepared_query("
            INSERT IGNORE INTO artist_usage (artist_id, role, uses)
            SELECT aa.ArtistID, ta.Importance, count(*) AS uses
            FROM torrents_artists ta
            INNER JOIN artists_alias aa USING (AliasID)
            GROUP BY aa.ArtistID, ta.Importance
        ");
        $affected = self::$db->affected_rows();
        self::$db->commit();
        return $affected;
    }
}
