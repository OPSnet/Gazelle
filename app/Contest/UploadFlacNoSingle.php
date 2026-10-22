<?php

namespace Gazelle\Contest;

use Gazelle\Enum\UserStatus;

/* how many non-Single 100% flacs uploaded? */

class UploadFlacNoSingle extends AbstractContest {
    use TorrentLeaderboard;

    public function ranker(): array {
        return [
            "SELECT um.ID AS user_id,
                count(*)  AS nr,
                max(t.ID) AS last_torrent
            FROM users_main um
            INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            INNER JOIN torrents t ON (t.Userid = um.ID)
            INNER JOIN xbt_files_users xfu ON (xfu.fid = t.ID AND xfu.uid = t.UserID)
            INNER JOIN torrents_group g ON (t.GroupID = g.ID)
            INNER JOIN release_type r ON (g.ReleaseType = r.ID)
            WHERE xfu.remaining = 0
                AND t.Format = 'FLAC'
                AND (t.Media IN ('Vinyl', 'WEB', 'SACD')
                    OR (t.Media = 'CD'
                        AND t.HasLog      = '1'
                        AND t.HasCue      = '1'
                        AND t.LogChecksum = '1'
                        AND t.LogScore    = 100
                    )
                )
                AND r.Name != 'Single'
                AND um.Enabled = ?
                AND ula.last_access >= ?
                AND t.created BETWEEN ? AND ?
            GROUP By um.ID
            ",
            [UserStatus::enabled->value, $this->begin, $this->begin, $this->end]
        ];
    }

    public function participationStats(): array {
        return self::$db->rowAssoc("
            SELECT count(DISTINCT t.ID) AS total_entries,
                count(DISTINCT um.ID) AS total_users
            FROM contest c,
                users_main um
            INNER JOIN torrents t ON (t.Userid = um.ID)
            INNER JOIN xbt_files_users xfu ON (xfu.fid = t.ID AND xfu.uid = t.UserID)
            INNER JOIN torrents_group g ON (t.GroupID = g.ID)
            INNER JOIN release_type r ON (g.ReleaseType = r.ID)
            WHERE xfu.remaining = 0
                AND t.Format = 'FLAC'
                AND (t.Media IN ('Vinyl', 'WEB', 'SACD')
                    OR (t.Media = 'CD'
                        AND t.HasLog      = '1'
                        AND t.HasCue      = '1'
                        AND t.LogChecksum = '1'
                        AND t.LogScore    = 100
                    )
                )
                AND t.created BETWEEN c.date_begin AND c.date_end
                AND r.Name != 'Single'
                AND um.created <= c.date_end
                AND um.Enabled = ?
                AND c.contest_id = ?
            ", UserStatus::enabled->value, $this->id
        );
    }

    public function userPayout(): array {
        self::$db->prepared_query("
            WITH c AS (
                SELECT um.ID    AS user_id,
                    count(t.ID) AS total_entries
                FROM contest c,
                    users_main um
                INNER JOIN torrents t ON (t.UserID = um.ID)
                INNER JOIN xbt_files_users xfu ON (xfu.fid = t.ID AND xfu.uid = t.UserID)
                INNER JOIN torrents_group g ON (t.GroupID = g.ID)
                INNER JOIN release_type r ON (g.ReleaseType = r.ID)
                WHERE xfu.remaining = 0
                    AND t.created BETWEEN c.date_begin AND c.date_end
                    AND t.Format = 'FLAC'
                    AND (
                        t.Media IN ('Vinyl', 'WEB', 'SACD')
                        OR (t.Media           = 'CD'
                            AND t.HasLog      = '1'
                            AND t.HasCue      = '1'
                            AND t.LogChecksum = '1'
                            AND t.LogScore    = 100
                        )
                    )
                    AND r.Name != 'Single'
                    AND um.Enabled   = ?
                    AND c.contest_id = ?
                GROUP BY um.ID
            )
            SELECT um.ID                     AS user_id,
                coalesce(c.total_entries, 0) AS total_entries
            FROM users_main um
            INNER JOIN user_last_access ula ON (ula.user_id = um.ID)
            LEFT JOIN c ON (c.user_id = um.ID)
            WHERE um.Enabled = ?
                AND ula.last_access > (SELECT date_begin FROM contest WHERE contest_id = ?)
            ", UserStatus::enabled->value, $this->id,
               UserStatus::enabled->value, $this->id,
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }
}
