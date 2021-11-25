<?php

namespace Gazelle\Stats;

class Users extends \Gazelle\Base {
    /**
     * The annual flow of users: people registered and disabled
     * @return array keyed by month [month, joined, disabled]
     */
    public function flow(): array {
        if (!$flow = $this->cache->get_value('stat-user-timeline')) {
            /* Mysql does not implement a full outer join, so if there is a month with
             * no joiners, any banned users in that same month will not appear.
             * Mysql does not implement sequence generators as in Postgres, so if there
             * is a month without any joiners or banned, it will not appear at all.
             * Deal with it. - Spine
             */
            $this->db->prepared_query("
                SELECT J.Mon, J.n as Joined, coalesce(D.n, 0) as Disabled
                FROM (
                    SELECT DATE_FORMAT(JoinDate,'%Y%m') as M, DATE_FORMAT(JoinDate, '%b %Y') AS Mon, count(*) AS n
                    FROM users_info
                    WHERE JoinDate BETWEEN last_day(now()) - INTERVAL 13 MONTH + INTERVAL 1 DAY
                        AND last_day(now()) - INTERVAL 1 MONTH
                    GROUP BY M) J
                LEFT JOIN (
                    SELECT DATE_FORMAT(BanDate, '%Y%m') AS M, DATE_FORMAT(BanDate, '%b %Y') AS Mon, count(*) AS n
                    FROM users_info
                    WHERE BanDate BETWEEN last_day(now()) - INTERVAL 13 MONTH + INTERVAL 1 DAY
                        AND last_day(now()) - INTERVAL 1 MONTH
                    GROUP By M
                ) D USING (M)
                ORDER BY J.M;
            ");
            $flow = $this->db->to_array('Mon');
            $this->cache->cache_value('stat-user-timeline', $flow, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for Dec -> Jan
        }
        return $flow ?: [];
    }

    /**
     * Count of users aggregated by primary class
     * @return array [class name, user count]
     */
    public function classDistribution(): array {
        if (!$dist = $this->cache->get_value('stat-user-class')) {
            $this->db->prepared_query("
                SELECT p.Name, count(*) AS Users
                FROM users_main AS m
                INNER JOIN permissions AS p ON (m.PermissionID = p.ID)
                WHERE m.Enabled = '1'
                GROUP BY p.Name
                ORDER BY Users DESC
            ");
            $dist = $this->db->to_array('Name');
            $this->cache->cache_value('stat-user-class', $dist, 86400);
        }
        return $dist ?: [];
    }

    /**
     * Count of users aggregated by OS platform
     * @return array [platform, user count]
     */
    public function platformDistribution(): array {
        if (!$dist = $this->cache->get_value('stat-user-platform')) {
            $this->db->prepared_query("
                SELECT OperatingSystem, count(*) AS Users
                FROM users_sessions
                GROUP BY OperatingSystem
                ORDER BY Users DESC
            ");
            $dist = $this->db->to_array();
            $this->cache->cache_value('stat-user-platform', $dist, 86400);
        }
        return $dist ?: [];
    }

    /**
     * Count of users aggregated by browser
     * @return array [browser, user count]
     */
    public function browserDistribution(): array {
        if (!$dist = $this->cache->get_value('stat-user-browser')) {
            $this->db->prepared_query("
                SELECT Browser, count(*) AS Users
                FROM users_sessions
                GROUP BY Browser
                ORDER BY Users DESC
            ");
            $dist = $this->db->to_array();
            $this->cache->cache_value('stat-user-browser', $dist, 86400);
        }
        return $dist ?: [];
    }

    /**
     * Country aggregates.
     * TODO: this is really fucked
     *
     * @return array List of country
     * @return array Country rank
     * @return array Country user total
     * @return int Country with least users
     * @return int Country with most users
     * @return int Log increments
     */
    public function geodistribution(): array {
        if (![$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements] = $this->cache->get_value('geodistribution')) {
            $this->db->prepared_query("
                SELECT Code, Users FROM users_geodistribution
            ");
            $Data = $this->db->to_array();
            $Count = $this->db->record_count() - 1;

            if ($Count < 30) {
                $CountryMinThreshold = $Count;
            } else {
                $CountryMinThreshold = 30;
            }

            $CountryMax = ceil(log(Max(1, $Data[0][1])) / log(2)) + 1;
            $CountryMin = floor(log(Max(1, $Data[$CountryMinThreshold][1])) / log(2));

            $CountryRegions = ['RS' => ['RS-KM']]; // Count Kosovo as Serbia as it doesn't have a TLD
            foreach ($Data as $Key => $Item) {
                [$Country, $UserCount] = $Item;
                $Countries[] = $Country;
                $CountryUsers[] = number_format((((log($UserCount) / log(2)) - $CountryMin) / ($CountryMax - $CountryMin)) * 100, 2);
                $Rank[] = round((1 - ($Key / $Count)) * 100);

                if (isset($CountryRegions[$Country])) {
                    foreach ($CountryRegions[$Country] as $Region) {
                        $Countries[] = $Region;
                        $Rank[] = end($Rank);
                    }
                }
            }

            for ($i = $CountryMin; $i <= $CountryMax; $i++) {
                $LogIncrements[] = \Format::human_format(pow(2, $i));
            }
            $this->cache->cache_value('geodistribution', [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements], 86400 * 3);
        }
        return [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements];
    }

    public function refresh(): int {
        $this->db->prepared_query("
            CREATE TEMPORARY TABLE user_summary_new LIKE user_summary
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, artist_added_total)
                SELECT ta.UserID, count(*)
                FROM torrents_artists ta
                GROUP BY ta.UserID
            ON DUPLICATE KEY UPDATE
                artist_added_total = VALUES(artist_added_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, collage_total, collage_contrib)
                SELECT ui.UserID,
                    coalesce(CT.collage_total, 0),
                    coalesce(CC.collage_contrib, 0)
                FROM users_info ui
                LEFT JOIN (
                    SELECT c.UserID, count(*) as collage_total
                    FROM collages c
                    WHERE c.Deleted = '0'
                    GROUP BY c.UserID
                    ) CT USING (UserID)
                LEFT JOIN (
                    SELECT ct.UserID, count(DISTINCT ct.CollageID) as collage_contrib
                    FROM collages_torrents AS ct
                    INNER JOIN collages c ON (c.ID = ct.CollageID)
                    WHERE c.Deleted = '0'
                    GROUP BY ct.UserID
                    ) CC USING (UserID)
                WHERE coalesce(CT.collage_total, CC.collage_contrib) IS NOT NULL
            ON DUPLICATE KEY UPDATE
                collage_total = VALUES(collage_total),
                collage_contrib = VALUES(collage_contrib)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, download_total, download_unique)
                SELECT ud.UserID,
                   count(*) AS total,
                   count(DISTINCT ud.TorrentID) AS 'unique'
               FROM users_downloads AS ud
               INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
               GROUP BY ud.UserID
            ON DUPLICATE KEY UPDATE
                download_total = VALUES(download_total),
                download_unique = VALUES(download_unique)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, fl_token_total)
                SELECT uf.UserID, count(*) AS fl_token_total
                FROM users_freeleeches uf
                GROUP BY uf.UserID
            ON DUPLICATE KEY UPDATE
                fl_token_total = VALUES(fl_token_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, forum_post_total)
                SELECT fp.AuthorID, count(*) AS forum_post_total
                FROM forums_posts fp
                GROUP BY fp.AuthorID
            ON DUPLICATE KEY UPDATE
                forum_post_total = VALUES(forum_post_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, forum_thread_total)
                SELECT ft.AuthorID, count(*) AS forum_thread_total
                FROM forums_topics ft
                GROUP BY ft.AuthorID
            ON DUPLICATE KEY UPDATE
                forum_thread_total = VALUES(forum_thread_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, invited_total)
                SELECT ui.Inviter, count(*) as invited_total
                FROM users_info ui
                WHERE ui.Inviter IS NOT NULL
                GROUP BY ui.Inviter
            ON DUPLICATE KEY UPDATE
                invited_total = VALUES(invited_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, perfect_flac_total, perfecter_flac_total, unique_group_total, upload_total)
                SELECT t.UserID,
                    sum(if(
                        t.Format = 'FLAC'
                        AND (
                            (t.Media = 'CD' AND t.LogScore = 100)
                            OR (t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT'))
                        ),
                        1, 0)) AS perfect_flac_total,
                    sum(if(
                        t.Format = 'FLAC'
                        AND (
                            (t.Media = 'CD' AND t.LogScore = 100)
                            OR t.Media IN ('Cassette', 'DAT')
                            OR (t.Media IN ('Vinyl', 'DVD', 'Soundboard', 'SACD', 'BD') AND t.Encoding = '24bit Lossless')
                        ),
                        1, 0)) AS perfecter_flac_total,
                    count(DISTINCT GroupID) AS unique_group_total,
                    count(*) AS upload_total
                FROM torrents t
                GROUP BY t.UserID
            ON DUPLICATE KEY UPDATE
                perfect_flac_total = VALUES(perfect_flac_total),
                perfecter_flac_total = VALUES(perfecter_flac_total),
                unique_group_total = VALUES(unique_group_total),
                upload_total = VALUES(upload_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, request_bounty_size, request_bounty_total)
                SELECT r.FillerID,
                    coalesce(sum(rv.Bounty), 0) AS size,
                    count(DISTINCT r.ID) AS total
                FROM requests AS r
                LEFT JOIN requests_votes AS rv ON (r.ID = rv.RequestID)
                WHERE r.FillerID != 0
                GROUP BY r.FillerID
            ON DUPLICATE KEY UPDATE
                request_bounty_size = VALUES(request_bounty_size),
                request_bounty_total = VALUES(request_bounty_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, request_created_size, request_created_total)
                SELECT r.UserID,
                    coalesce(sum(rv.Bounty), 0) AS size,
                    count(*) AS total
                FROM requests AS r
                LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID AND rv.UserID = r.UserID)
                GROUP BY r.UserID
            ON DUPLICATE KEY UPDATE
                request_created_size = VALUES(request_created_size),
                request_created_total = VALUES(request_created_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, request_vote_size, request_vote_total)
                SELECT rv.UserID,
                    coalesce(sum(rv.Bounty), 0) AS size,
                    count(*) AS total
                FROM requests_votes rv
                GROUP BY rv.UserID
            ON DUPLICATE KEY UPDATE
                request_vote_size = VALUES(request_vote_size),
                request_vote_total = VALUES(request_vote_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, leech_total)
                SELECT xfu.uid,
                    count(DISTINCT xfu.fid)
                FROM xbt_files_users AS xfu
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                WHERE xfu.remaining > 0
                GROUP BY xfu.uid
            ON DUPLICATE KEY UPDATE
                leech_total = VALUES(leech_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, seeding_total)
                SELECT xfu.uid,
                    count(DISTINCT xfu.fid)
                FROM xbt_files_users AS xfu
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                WHERE xfu.remaining = 0
                GROUP BY xfu.uid
            ON DUPLICATE KEY UPDATE
                seeding_total = VALUES(seeding_total)
        ");

        $this->db->prepared_query("
            INSERT INTO user_summary_new (user_id, snatch_total, snatch_unique)
                SELECT xs.uid,
                   count(*) AS total,
                   count(DISTINCT xs.fid) AS 'unique'
               FROM xbt_snatched AS xs
               INNER JOIN torrents AS t ON (t.ID = xs.fid)
               GROUP BY xs.uid
            ON DUPLICATE KEY UPDATE
                snatch_total = VALUES(snatch_total),
                snatch_unique = VALUES(snatch_unique)
        ");

        $this->db->begin_transaction();
        $this->db->prepared_query("
            DELETE FROM user_summary
        ");
        $this->db->prepared_query("
            INSERT INTO user_summary
            SELECT * FROM user_summary_new
        ");
        $processed = $this->db->affected_rows();
        $this->db->commit();
        return $processed;
    }

    public function registerActivity(string $tableName, int $days): int {
        if ($days > 0) {
            $this->db->prepared_query("
                 DELETE FROM $tableName WHERE Time < now() - INTERVAL ? DAY
                 ", $days
            );
        }
        $this->db->prepared_query("
            INSERT INTO $tableName (UserID, Uploaded, Downloaded, BonusPoints, Torrents, PerfectFLACs)
            SELECT um.ID, uls.Uploaded, uls.Downloaded, coalesce(ub.points, 0), COUNT(t.ID) AS Torrents, COALESCE(p.Perfects, 0) AS PerfectFLACs
            FROM users_main um
            INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
            LEFT JOIN user_bonus ub ON (ub.user_id = um.ID)
            LEFT JOIN torrents t ON (t.UserID = um.ID)
            LEFT JOIN
            (
                SELECT UserID, count(*) AS Perfects
                FROM torrents
                WHERE Format = 'FLAC'
                    AND (
                        Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT')
                        OR
                        (Media = 'CD' AND LogScore = 100)
                    )
                GROUP BY UserID
            ) p ON (p.UserID = um.ID)
            GROUP BY um.ID
        ");
        return $this->db->affected_rows();
    }

    public function browserList(): array {
        $this->db->prepared_query("
            SELECT Browser     AS name,
                BrowserVersion AS `version`,
                count(*)       AS total
            FROM users_sessions
            WHERE Browser IS NOT NULL
            GROUP BY name, version
            ORDER BY total DESC, name, version
        ");
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function operatingSystemList(): array {
        $this->db->prepared_query("
            SELECT OperatingSystem     AS name,
                OperatingSystemVersion AS version,
                count(*)               AS total
            FROM users_sessions
            WHERE OperatingSystem IS NOT NULL
            GROUP BY name, version
            ORDER BY total DESC, name, version
        ");
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }
}
