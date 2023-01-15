<?php

namespace Gazelle\Contest;

/* how many perfect 100% CD flacs uploaded? */

class UploadPerfectFlac extends AbstractContest {

    use TorrentLeaderboard;

    public function ranker(): array {
        return [
            "SELECT um.ID AS userid,
                count(*) AS nr,
                max(t.ID) AS last_torrent
            FROM users_main um
            INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            INNER JOIN torrents t ON (t.Userid = um.ID)
            WHERE um.Enabled = '1'
                AND ula.last_access >= ?
                AND t.Format = 'FLAC'
                AND t.Media = 'CD'
                AND t.HasLog = '1'
                AND t.HasCue = '1'
                AND t.LogScore = 100
                AND t.LogChecksum = '1'
                AND t.Time BETWEEN ? AND ?
            GROUP By um.ID
            ",
            [ $this->begin, $this->begin, $this->end ]
        ];
    }

    public function participationStats(): array {
        return self::$db->rowAssoc("
            SELECT count(*) AS total_entries,
                count(DISTINCT um.ID) AS total_users
            FROM contest c,
                users_main um
            INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            INNER JOIN torrents t ON (t.Userid = um.ID)
            WHERE um.Enabled = '1'
                AND ula.last_access >= c.date_begin
                AND t.Format = 'FLAC'
                AND t.Time BETWEEN c.date_begin AND c.date_end
                AND t.Media = 'CD'
                AND t.HasLog = '1'
                AND t.HasCue = '1'
                AND t.LogScore = 100
                AND t.LogChecksum = '1'
                AND c.contest_id = ?
            ", $this->id
        );
    }

    public function userPayout(int $enabledUserBonus, int $contestBonus, int $perEntryBonus): array {
        self::$db->prepared_query("
            SELECT um.ID,
                count(t.ID) AS total_entries,
                ? AS enabled_bonus,
                CASE WHEN count(t.ID) > 0 THEN ? ELSE 0 END AS contest_bonus,
                count(t.ID) * ? AS entries_bonus
            FROM contest c,
                users_main um
            INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            LEFT JOIN torrents t ON (t.UserID = um.ID)
            WHERE um.Enabled = '1'
                AND ula.last_access >= c.date_begin
                AND
                    (t.ID IS NULL
                    OR (t.Format = 'FLAC'
                        AND t.Time BETWEEN c.date_begin AND c.date_end
                        AND t.Media = 'CD'
                        AND t.HasLog = '1'
                        AND t.HasCue = '1'
                        AND t.LogScore = 100
                        AND t.LogChecksum = '1'
                    )
                )
                AND c.contest_id = ?
            GROUP BY um.ID
            ", $enabledUserBonus, $contestBonus, $perEntryBonus, $this->id
        );
        return self::$db->to_array('ID', MYSQLI_ASSOC, false);
    }
}
