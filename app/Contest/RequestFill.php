<?php

namespace Gazelle\Contest;

/* how many requests filled */

class RequestFill extends AbstractContest {

    public function leaderboard(int $limit, int $offset): array {
        // TODO
        return [];
    }

    public function ranker(): array {
        return [
            "SELECT r.FillerID AS userid,
                count(*) AS nr,
                max(if(r.TimeFilled = LAST.TimeFilled AND r.TimeAdded < ?, TorrentID, NULL)) AS last_torrent
            FROM requests r
            INNER JOIN (
                SELECT r.FillerID,
                    MAX(r.TimeFilled) AS TimeFilled
                FROM requests r
                INNER JOIN users_main um ON (um.ID = r.FillerID)
                INNER JOIN torrents t ON (t.ID = r.TorrentID)
                WHERE um.Enabled = '1'
                    AND r.FillerId != r.UserID
                    AND r.TimeAdded < ?
                    AND r.TimeFilled BETWEEN ? AND ?
                GROUP BY r.FillerID
            ) LAST USING (FillerID)
            WHERE r.FillerId != r.UserID
                AND r.TimeAdded < ?
                AND r.TimeFilled BETWEEN ? AND ?
            GROUP BY r.FillerID
            ",
            [
                $this->begin,
                $this->begin,
                $this->begin, $this->end,
                $this->begin,
                $this->begin, $this->end,
            ]
        ];
    }

    public function participationStats(): array {
        return self::$db->row("
            SELECT count(*) AS total_entries,
                count(DISTINCT um.ID) AS total_users
            FROM contest c,
                users_main um
            INNER JOIN requests r ON (r.FillerID = um.ID)
            WHERE um.Enabled = '1'
                AND r.FillerId != r.UserID
                AND r.TimeFilled BETWEEN c.date_begin AND c.date_end
                AND r.TimeAdded < c.date_begin
                AND c.contest_id = ?
            ", $this->id
        );
    }

    public function userPayout(float $enabledUserBonus, float $contestBonus, float $perEntryBonus): array {
        self::$db->prepared_query("
            SELECT um.ID, um.Username,
                count(r.ID) AS total_entries,
                ? AS enabled_bonus,
                CASE WHEN count(r.ID) > 0 THEN ? ELSE 0 END AS contest_bonus,
                count(r.ID) * ? AS entries_bonus
            FROM contest c,
                users_main um
            INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            LEFT JOIN requests r ON (r.FillerID = um.ID)
            WHERE um.Enabled = '1'
                AND ula.last_access >= c.date_begin
                AND (
                    r.ID IS NULL
                    OR
                    r.TimeFilled BETWEEN c.date_begin AND c.date_end
                )
                AND c.contest_id = ?
            GROUP BY um.ID
            ", $enabledUserBonus, $contestBonus, $perEntryBonus, $this->id
        );
        return self::$db->to_array('ID', MYSQLI_ASSOC) ?? [];
    }

    public function requestPairs() {
        $key = "contest_pairs_" . $this->id;
        if (($pairs = self::$cache->get_value($key)) === false) {
            self::$db->prepared_query("
                SELECT r.FillerID, r.UserID, count(*) AS nr
                FROM requests r
                WHERE r.TimeFilled BETWEEN ? AND ?
                GROUP BY r.FillerID, r.UserId
                HAVING count(*) > 1
                ORDER BY count(*) DESC, r.FillerID ASC
                LIMIT 100
                ", $this->begin, $this->end
            );
            $pairs = self::$db->to_array(false, MYSQLI_ASSOC);
            self::$cache->cache_value('contest_pairs_' . $this->id, $pairs, 3600);
        }
        return $pairs;
    }
}
