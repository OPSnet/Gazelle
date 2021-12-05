<?php

namespace Gazelle\Schedule\Tasks;

class ArtistUsage extends \Gazelle\Schedule\Task
{
    public function run()
    {
        self::$db->prepared_query('
            INSERT INTO artist_usage (artist_id, role, uses)
            SELECT ArtistID, Importance, count(*) AS uses
            FROM torrents_artists
            GROUP BY ArtistID, Importance
            ON DUPLICATE KEY UPDATE uses = VALUES(uses)
        ');
        $this->processed = self::$db->affected_rows();
    }
}
