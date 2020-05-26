<?php

namespace Gazelle;

class Contest extends Base {

    const CACHE_CONTEST_TYPE = 'contest_type';
    const CACHE_CONTEST = 'contest.%d';
    const CACHE_CURRENT = 'contest_current';

    private $type;

    public function __construct () {
        parent::__construct();
        $this->type = $this->cache->get_value(self::CACHE_CONTEST_TYPE);
        if ($this->type === false) {
            $this->db->query("SELECT ID, Name FROM contest_type ORDER BY ID");
            $this->type = $this->db->to_array('ID');
            $this->cache->cache_value(self::CACHE_CONTEST_TYPE, $this->type, 86400 * 7);
        }
    }

    public function get_type () {
        return $this->type;
    }

    public function get_list () {
        $this->db->query("
            SELECT c.ID, c.Name, c.DateBegin, c.DateEnd, t.ID AS ContestType, (cbp.BonusPoolID IS NOT NULL) AS BonusPool, cbp.Status AS BonusStatus
            FROM contest c
            INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
            LEFT JOIN contest_has_bonus_pool cbp ON (cbp.ContestID = c.ID)
            ORDER BY c.DateBegin DESC
         ");
         return $this->db->to_array();
    }

    public function get_contest ($Id) {
        $key = sprintf(self::CACHE_CONTEST, $Id);
        $Contest = $this->cache->get_value($key);
        if ($Contest === false) {
            $this->db->prepared_query('
                SELECT c.ID, t.Name AS ContestType, c.Name, c.Banner, c.WikiText, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd,
                    coalesce(cbp.BonusPoolID, 0) AS BonusPool,
                    cbp.Status AS BonusStatus,
                    CASE WHEN now() BETWEEN c.DateBegin AND c.DateEnd THEN 1 ELSE 0 END AS is_open,
                    CASE WHEN cbp.BonusPoolID IS NOT NULL AND cbp.Status = ? AND now() > c.DateEnd THEN 1 ELSE 0 END AS payout_ready
                FROM contest c
                INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
                LEFT JOIN contest_has_bonus_pool cbp ON (cbp.ContestID = c.ID)
                WHERE c.ID = ?
                ', 'open', $Id
            );
            if ($this->db->has_results()) {
                $Contest = $this->db->next_record(MYSQLI_ASSOC);
                $this->cache->cache_value($key, $Contest, 86400 * 3);
            }
        }
        return $Contest;
    }

    public function get_current_contest () {
        $Contest = $this->cache->get_value(self::CACHE_CURRENT);
        if ($Contest === false) {
            $this->db->prepared_query('
                SELECT c.ID, t.Name AS ContestType, c.Name, c.Banner, c.WikiText, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd,
                    coalesce(cbp.BonusPoolID, 0) AS BonusPool,
                    cbp.Status AS BonusStatus,
                    CASE WHEN now() BETWEEN c.DateBegin AND c.DateEnd THEN 1 ELSE 0 END AS is_open,
                    CASE WHEN cbp.BonusPoolID IS NOT NULL AND cbp.Status = ? AND now() > c.DateEnd THEN 1 ELSE 0 END AS payout_ready
                FROM contest c
                INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
                LEFT JOIN contest_has_bonus_pool cbp ON (cbp.ContestID = c.ID)
                WHERE c.DateEnd = (select max(DateEnd) from contest)
            ', 'open');
            if ($this->db->has_results()) {
                $Contest = $this->db->next_record(MYSQLI_ASSOC);
                // Cache this for three days
                $this->cache->cache_value(sprintf(self::CACHE_CONTEST, $Contest['ID']), $Contest, 86400 * 3);
                $this->cache->cache_value(self::CACHE_CURRENT, $Contest, 86400 * 3);
            }
        }
        return $Contest;
    }

    public function get_prior_contests () {
        $Prior = $this->cache->get_value('contest_prior');
        if ($Prior === false) {
            $this->db->query("
                SELECT c.ID
                FROM contest c
                WHERE c.DateBegin < NOW()
                /* AND ... we may want to think about excluding certain past contests */
                ORDER BY c.DateBegin ASC
            ");
            if ($this->db->has_results()) {
                $Prior = $this->db->to_array(false, MYSQLI_BOTH);
                $this->cache->cache_value('contest_prior', $Prior, 86400 * 3);
            }
        }
        return $Prior;
    }

    private function leaderboard_query ($Contest) {
        /* only called from schedule, don't need to worry about caching this */
        switch ($Contest['ContestType']) {
            case 'upload_flac':
                /* how many 100% flacs uploaded? */
                $sql = "
                    SELECT u.ID AS userid,
                        count(*) AS nr,
                        max(t.ID) AS last_torrent
                    FROM users_main u
                    INNER JOIN torrents t ON (t.Userid = u.ID)
                    WHERE t.Format = 'FLAC'
                        AND t.Time BETWEEN ? AND ?
                        AND (
                            t.Media IN ('Vinyl', 'WEB')
                            OR (t.Media = 'CD'
                                AND t.HasLog = '1'
                                AND t.HasCue = '1'
                                AND t.LogScore = 100
                                AND t.LogChecksum = '1'
                            )
                        )
                    GROUP By u.ID
                ";
                $args = [
                    $Contest['DateBegin'],
                    $Contest['DateEnd']
                ];
                break;

            case 'upload_flac_no_single':
                /* how many non-Single 100% flacs uploaded? */
                $sql = "
                    SELECT u.ID AS userid,
                        count(*) AS nr,
                        max(t.ID) AS last_torrent
                    FROM users_main u
                    INNER JOIN torrents t ON (t.Userid = u.ID)
                    INNER JOIN torrents_group g ON (t.GroupID = g.ID)
                    INNER JOIN release_type r ON (g.ReleaseType = r.ID)
                    WHERE r.Name != 'Single'
                        AND t.Format = 'FLAC'
                        AND t.Time BETWEEN ? AND ?
                        AND (
                            t.Media IN ('Vinyl', 'WEB', 'SACD')
                            OR (t.Media = 'CD'
                                AND t.HasLog = '1'
                                AND t.HasCue = '1'
                                AND t.LogScore = 100
                                AND t.LogChecksum = '1'
                            )
                        )
                    GROUP By u.ID
                ";
                $args = [
                    $Contest['DateBegin'],
                    $Contest['DateEnd']
                ];
                break;

            case 'request_fill':
                /* how many requests filled */
                $sql = "
                    SELECT r.FillerID AS userid,
                        count(*) AS nr,
                        max(if(r.TimeFilled = LAST.TimeFilled AND r.TimeAdded < ?, TorrentID, NULL)) AS last_torrent
                    FROM requests r
                    INNER JOIN (
                        SELECT r.FillerID,
                            MAX(r.TimeFilled) AS TimeFilled
                        FROM requests r
                        INNER JOIN users_main u ON (r.FillerID = u.ID)
                        INNER JOIN torrents t ON (r.TorrentID = t.ID)
                        WHERE r.TimeFilled BETWEEN ? AND ?
                            AND r.FIllerId != r.UserID
                            AND r.TimeAdded < ?
                        GROUP BY r.FillerID
                    ) LAST USING (FillerID)
                    WHERE r.TimeFilled BETWEEN ? AND ?
                        AND r.FIllerId != r.UserID
                        AND r.TimeAdded < ?
                    GROUP BY r.FillerID
                    ";
                $args = [
                    $Contest['DateBegin'],
                    $Contest['DateBegin'],
                    $Contest['DateEnd'],
                    $Contest['DateBegin'],
                    $Contest['DateBegin'],
                    $Contest['DateEnd'],
                    $Contest['DateBegin']
                ];
                break;
            default:
                $sql = null;
                $args = [];
                break;
        }
        return [$sql, $args];
    }

    public function calculate_leaderboard () {
        $this->db->query("
            SELECT c.ID
            FROM contest c
            INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
            WHERE c.DateEnd > now() - INTERVAL 1 MONTH
            ORDER BY c.DateEnd DESC
        ");
        $contest_id = [];
        while ($this->db->has_results()) {
            $c = $this->db->next_record();
            if (isset($c['ID'])) {
                $contest_id[] = $c['ID'];
            }
        }
        foreach ($contest_id as $id) {
            $Contest = $this->get_contest($id);
            list($subquery, $args) = $this->leaderboard_query($Contest);
            array_unshift($args, $id);
            if ($subquery) {
                $this->db->query("BEGIN");
                $this->db->prepared_query('DELETE FROM contest_leaderboard WHERE ContestID = ?', $id);
                $this->db->prepared_query("
                    INSERT INTO contest_leaderboard
                    SELECT ?, LADDER.userid,
                        LADDER.nr,
                        T.ID,
                        TG.Name,
                        group_concat(TA.ArtistID),
                        group_concat(AG.Name order by AG.Name separator 0x1),
                        T.Time
                    FROM torrents_group TG
                    LEFT JOIN torrents_artists TA ON (TA.GroupID = TG.ID)
                    LEFT JOIN artists_group AG ON (AG.ArtistID = TA.ArtistID)
                    INNER JOIN torrents T ON (T.GroupID = TG.ID)
                    INNER JOIN (
                        $subquery
                    ) LADDER on (LADDER.last_torrent = T.ID)
                    GROUP BY
                        LADDER.nr,
                        T.ID,
                        TG.Name,
                        T.Time
                ", ...$args);
                $this->db->query("COMMIT");
                $this->cache->delete_value('contest_leaderboard_' . $id);
                switch ($Contest['ContestType']) {
                    case 'upload_flac':
                        $this->db->prepared_query("
                            SELECT count(*) AS nr
                            FROM torrents t
                            WHERE t.Format = 'FLAC'
                                AND t.Time BETWEEN ? AND ?
                                AND (
                                    t.Media IN ('Vinyl', 'WEB')
                                    OR (t.Media = 'CD'
                                        AND t.HasLog = '1'
                                        AND t.HasCue = '1'
                                        AND t.LogScore = 100
                                        AND t.LogChecksum = '1'
                                    )
                                )
                            ", $Contest['DateBegin'], $Contest['DateEnd']
                        );
                        break;
                    case 'upload_flac_no_single':
                        $this->db->prepared_query("
                            SELECT count(*) AS nr
                            FROM torrents t
                            INNER JOIN torrents_group g ON (t.GroupID = g.ID)
                            INNER JOIN release_type r ON (g.ReleaseType = r.ID)
                            WHERE r.Name != 'Single'
                                AND t.Format = 'FLAC'
                                AND t.Time BETWEEN ? AND ?
                                AND (
                                    t.Media IN ('Vinyl', 'WEB', 'SACD')
                                    OR (t.Media = 'CD'
                                        AND t.HasLog = '1'
                                        AND t.HasCue = '1'
                                        AND t.LogScore = 100
                                        AND t.LogChecksum = '1'
                                    )
                                )
                            ", $Contest['DateBegin'], $Contest['DateEnd']
                        );
                        break;
                    case 'request_fill':
                        $this->db->prepared_query("
                            SELECT
                                count(*) AS nr
                            FROM requests r
                            INNER JOIN users_main u ON (r.FillerID = u.ID)
                            WHERE r.TimeFilled BETWEEN ? AND ?
                                AND r.FIllerId != r.UserID
                                AND r.TimeAdded < ?
                            ", $Contest['DateBegin'], $Contest['DateEnd'], $Contest['DateBegin']
                        );
                        break;
                    default:
                        $this->db->prepared_query("SELECT 0");
                        break;
                }
                $this->cache->cache_value(
                    "contest_leaderboard_total_{$Contest['ID']}",
                    $this->db->has_results() ? $this->db->next_record()[0] : 0,
                    3600 * 6
                );
                $this->get_leaderboard($id, false);
            }
        }
    }

    public function get_leaderboard ($Id, $UseCache = true) {
        $Contest = $this->get_contest($Id);
        $Key = "contest_leaderboard_{$Contest['ID']}";
        $Leaderboard = $this->cache->get_value($Key);
        if (!$UseCache || $Leaderboard === false) {
            $this->db->query("
            SELECT
                l.UserID,
                l.FlacCount,
                l.LastTorrentID,
                l.LastTorrentNAme,
                l.ArtistList,
                l.ArtistNames,
                l.LastUpload
            FROM contest_leaderboard l
            WHERE l.ContestID = {$Contest['ID']}
            ORDER BY l.FlacCount DESC, l.LastUpload ASC, l.UserID ASC
            LIMIT {$Contest['MaxTracked']}");
            $Leaderboard = $this->db->to_array(false, MYSQLI_BOTH);
            $this->cache->cache_value($Key, $Leaderboard, 60 * 20);
        }
        return $Leaderboard;
    }

    public function calculate_request_pairs () {
        $Contest = $this->get_current_contest();
        if ($Contest['ContestType'] != 'request_fill') {
            $Pairs = [];
        }
        else {
            $this->db->query("
                SELECT r.FillerID, r.UserID, count(*) AS nr
                FROM requests r
                WHERE r.TimeFilled BETWEEN '{$Contest['DateBegin']}' AND '{$Contest['DateEnd']}'
                GROUP BY
                    r.FillerID, r.UserId
                HAVING count(*) > 1
                ORDER BY
                    count(*) DESC, r.FillerID ASC
                LIMIT 100
            ");
            $Pairs = $this->db->to_array(false, MYSQLI_BOTH);
        }
        $this->cache->cache_value('contest_pairs_' . $Contest['ID'], $Pairs, 60 * 20);
    }

    public function get_request_pairs ($UseCache = true) {
        $Contest = $this->get_current_contest();
        $Key = "contest_pairs_{$Contest['ID']}";
        if (($Pairs = $this->cache->get_value($Key)) === false) {
            $this->calculate_request_pairs();
            $Pairs = $this->cache->get_value($Key);
        }
        return $Pairs;
    }

    public function calculate_pool_payout ($id) {
        list($total_torrents, $total_users) = $this->get_upload_stats($id);
        $bonuspool = new \Gazelle\BonusPool($this->get_contest($id)['BonusPool']);
        return [
            'torrent' => $total_torrents,
            'user'    => $total_users,
            'bonus'   => $bonuspool->getTotalSent()
        ];
    }

    public function get_upload_stats ($id) {
        $this->db->prepared_query("
            SELECT count(*) AS nr_torrents, count(DISTINCT um.ID) AS nr_users
            FROM contest c,
                users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            INNER JOIN torrents t ON (t.Userid = um.ID)
            INNER JOIN torrents_group g ON (t.GroupID = g.ID)
            INNER JOIN release_type r ON (g.ReleaseType = r.ID)
            WHERE
                c.ID = ?
                AND um.Enabled = '1'
                AND ui.JoinDate <= c.DateEnd
                AND r.Name != 'Single'
                AND t.Format = 'FLAC'
                AND t.Time BETWEEN c.DateBegin AND c.DateEnd
                AND (
                    t.Media IN ('Vinyl', 'WEB', 'SACD')
                    OR (t.Media = 'CD'
                        AND t.HasLog = '1'
                        AND t.HasCue = '1'
                        AND t.LogScore = 100
                        AND t.LogChecksum = '1'
                    )
                )
        ", $id);
        return $this->db->next_record();
    }

    public function schedule_payout () {
        $this->db->prepared_query('
            SELECT c.ID
            FROM contest c
            INNER JOIN contest_has_bonus_pool cbp ON (cbp.ContestID = c.ID)
            WHERE c.DateEnd < now()
                AND cbp.Status = ?
            ', 'ready'
        );
        $Contests = $this->db->to_array(false, MYSQLI_NUM);
        $total_participants = 0;
        foreach ($Contests as $c) {
            $total_participants += $this->do_payout($c[0]);
            $this->set_payment_closed($c[0]);
        }
        return $total_participants;
    }

    protected function do_payout ($id) {
        $total = $this->calculate_pool_payout($id);
        $bonus = $total['bonus'];
        $enabled_user_bonus    = $bonus * 0.05 / \Users::get_enabled_users_count();
        $contest_participation = $bonus * 0.1 / $total['user'];
        $per_entry_bonus       = $bonus * 0.85 / $total['torrent'];

        $enabled_user_bonus_fmt    = number_format($enabled_user_bonus, 2);
        $contest_participation_fmt = number_format($contest_participation, 2);
        $per_entry_bonus_fmt       = number_format($per_entry_bonus, 2);

        $this->db->prepared_query("
            SELECT um.ID, um.Username, count(t.ID) AS nr_entries, ? AS enabled_bonus,
                CASE WHEN count(t.ID) > 0 THEN ? ELSE 0 END AS participated_bonus,
                count(t.ID) * ? AS entries_bonus
            FROM contest c,
                users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            LEFT JOIN torrents t ON (t.UserID = um.ID)
            LEFT JOIN torrents_group g ON (t.GroupID = g.ID)
            LEFT JOIN release_type r ON (g.ReleaseType = r.ID)
            WHERE
                c.ID = ?
                AND um.Enabled = '1'
                AND ui.JoinDate <= c.DateEnd
                AND
                    (t.ID IS NULL
                    OR (
                            r.Name != 'Single'
                        AND t.Format = 'FLAC'
                        AND t.Time BETWEEN c.DateBegin AND c.DateEnd
                        AND (
                            t.Media IN ('Vinyl', 'WEB', 'SACD')
                            OR (t.Media = 'CD'
                                AND t.HasLog = '1'
                                AND t.HasCue = '1'
                                AND t.LogScore = 100
                                AND t.LogChecksum = '1'
                            )
                        )
                    )
                )
            GROUP BY um.ID
            ", $enabled_user_bonus, $contest_participation, $per_entry_bonus, $id
        );
        $participants = $this->db->to_array('ID', MYSQLI_ASSOC);
        $contest = $this->get_contest($id);
        $bonus = new \Gazelle\Bonus;

        $report = fopen(TMPDIR . "/payout-contest-$id.txt", 'a');
        foreach ($participants as $p) {
            if ($p['nr_entries'] == 0) {
                $total_gain      = $enabled_user_bonus;
                $total_gain_fmt  = number_format($enabled_user_bonus, 2);
                $msg = <<<END_MSG
Dear {$p['Username']},

During {$contest['Name']} that ran from {$contest['DateBegin']} to {$contest['DateEnd']}, you didn't upload anything. So sad, because you missed out on {$contest_participation_fmt} bonus points just for participating even once, and an additional {$per_entry_bonus_fmt} points for each successful upload.

All is not lost, because thanks to the love and generosity of the Orpheus community, you have been granted {$total_gain_fmt} bonus points just for being here, enjoy!

<3
Orpheus Staff
END_MSG;
            } else {
                $entries         = $p['nr_entries'] == 1 ? 'entry' : 'entries';
                $entry_bonus     = $per_entry_bonus * $p['nr_entries'];
                $total_gain      = $enabled_user_bonus + $contest_participation + $entry_bonus;
                $entry_bonus_fmt = number_format($entry_bonus, 2);
                $total_gain_fmt  = number_format($total_gain, 2);
                $msg = <<<END_MSG
Dear {$p['Username']},

During {$contest['Name']} that ran from {$contest['DateBegin']} to {$contest['DateEnd']}, you uploaded {$p['nr_entries']} {$entries}. Each upload turned out to be worth {$per_entry_bonus_fmt} bonus points, so your uploading activity earnt you a total of {$entry_bonus_fmt} points!

On top of that, just because you participated, you have been awarded a further {$contest_participation_fmt} points. And since you are awesome and are on Orpheus anyway, the cherry on top is an additional {$enabled_user_bonus_fmt} points.

All in all, you have been granted {$total_gain_fmt} bonus points. Enjoy!

<3
Orpheus Staff
END_MSG;
            }
            $bonus->addPoints($p['ID'], $total_gain);
            \Misc::send_pm($p['ID'], 0, "You have received {$total_gain_fmt} bonus points!", $msg);
            $this->db->prepared_query("
                UPDATE users_info
                SET AdminComment = CONCAT(?, AdminComment)
                WHERE UserID = ?
                ", sqltime() . " - {$total_gain_fmt} BP added for {$p['nr_entries']} entries in {$contest['Name']}\n\n", $p['ID']
            );
            fwrite($report, sqltime() . " {$p['Username']} ({$p['ID']}) n={$p['nr_entries']} t={$total_gain_fmt}\n");
            fflush($report);
        }
        fclose($report);
        return count($participants);
    }

    public function set_payment_ready ($id) {
        $this->db->prepared_query('
            UPDATE contest_has_bonus_pool
            SET Status = ?
            WHERE ContestID = ?
            ', 'ready', $id
        );
        $this->cache->delete_value(sprintf(self::CACHE_CONTEST, $id));
        return $this->db->affected_rows();
    }

    public function set_payment_closed ($id) {
        $this->db->prepared_query('
            UPDATE contest_has_bonus_pool
            SET Status = ?
            WHERE ContestID = ?
            ', 'paid', $id
        );
        $this->cache->delete_value(sprintf(self::CACHE_CONTEST, $id));
        return $this->db->affected_rows();
    }

    public function save ($params) {
        if (isset($params['cid'])) {
            $contest_id = $params['cid'];
            $this->db->prepared_query("
                UPDATE contest SET
                    Name = ?, Display = ?, MaxTracked = ?, DateBegin = ?, DateEnd = ?,
                    ContestTypeID = ?, Banner = ?, WikiText = ?
                WHERE ID = ?
                ", $params['name'], $params['display'], $params['maxtrack'], $params['date_begin'], $params['date_end'],
                    $params['type'], $params['banner'], $params['intro'],
                    $contest_id
            );
            if (isset($params['payment'])) {
                $this->set_payment_ready($contest_id);
            }
            $this->cache->delete_value(self::CACHE_CURRENT);
            $this->cache->delete_value(sprintf(self::CACHE_CONTEST, $contest_id));
        }
        elseif (isset($params['new'])) {
            $this->db->prepared_query("
                INSERT INTO contest (Name, Display, MaxTracked, DateBegin, DateEnd, ContestTypeID, Banner, WikiText)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ",
                    $params['name'], $params['display'], $params['maxtrack'], $params['date_begin'], $params['date_end'],
                    $params['type'], $params['banner'], $params['intro']
            );
            $contest_id = $this->db->inserted_id();

            if (array_key_exists('pool', $params)) {
                $this->db->prepared_query("INSERT INTO bonus_pool (Name, SinceDate, UntilDate) VALUES (?, ?, ?)",
                    $params['name'], $params['date_begin'], $params['date_end']
                );
                $pool_id = $this->db->inserted_id();
                $this->db->prepared_query("INSERT INTO contest_has_bonus_pool (ContestID, BonusPoolID) VALUES (?, ?)",
                    $contest_id, $pool_id
                );
            }
        }
        return $contest_id;
    }
}
