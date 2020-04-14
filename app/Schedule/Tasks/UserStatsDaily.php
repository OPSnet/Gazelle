<?php

namespace Gazelle\Schedule\Tasks;

class UserStatsDaily extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
            INSERT INTO users_stats_daily (UserID, Uploaded, Downloaded, BonusPoints, Torrents, PerfectFLACs)
            SELECT um.ID, uls.Uploaded, uls.Downloaded, coalesce(ub.points, 0), COUNT(t.ID) AS Torrents, COALESCE(p.Perfects, 0) AS PerfectFLACs
            FROM users_main um
            INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
            LEFT JOIN user_bonus ub ON (ub.user_id = um.ID)
            LEFT JOIN torrents t ON (t.UserID = um.ID)
            LEFT JOIN
            (
                SELECT UserID, count(*) AS Perfects
                FROM torrents
                WHERE( Format = 'FLAC'
                    AND (
                        Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT')
                        OR
                        (LogScore = 100 AND Media = 'CD')))
                GROUP BY UserID
            ) p ON (p.UserID = um.ID)
            GROUP BY um.ID
        ");
        $this->processed = $this->db->affected_rows();
    }
}
