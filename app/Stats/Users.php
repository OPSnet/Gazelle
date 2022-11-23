<?php

namespace Gazelle\Stats;

class Users extends \Gazelle\Base {

    protected const USER_BROWSER  = 'stat_user_browser';
    protected const USER_CLASS    = 'stat_user_class';
    protected const USER_PLATFORM = 'stat_user_platform';

    /**
     * The annual flow of users: people registered and disabled
     */
    public function flow(): array {
        $flow = self::$cache->get_value('stat-user-timeline');
        $flow = false;
        if ($flow === false) {
            /* Mysql does not implement a full outer join, so if there is a month with
             * no joiners, any banned users in that same month will not appear.
             * Mysql does not implement sequence generators as in Postgres, so if there
             * is a month without any joiners or banned, it will not appear at all.
             * Deal with it. - Spine
             */
            self::$db->prepared_query("
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
            $flow = self::$db->to_array('Mon', MYSQLI_ASSOC, false);
            self::$cache->cache_value('stat-user-timeline', $flow, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for Dec -> Jan
        }
        return $flow;
    }

    /**
     * Reformat the output of a label, total query to simplify the consumption by highcharts
     */
    protected function reformatDist(array $result): array {
        $dist = [];
        foreach ($result as $label => $total) {
            $dist[] = [
                'name' => $label,
                'y'    => $total,
            ];
        }
        return $dist;
    }

    /**
     * Users aggregated by browser
     */
    public function browserDistribution(): array {
        $dist = self::$cache->get_value(self::USER_BROWSER);
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT Browser AS label,
                    count(*) AS total
                FROM users_sessions
                GROUP BY label
                ORDER BY total DESC
            ");
            $dist = $this->reformatDist(self::$db->to_pair('label', 'total', false));
            self::$cache->cache_value(self::USER_BROWSER, $dist, 86400);
        }
        return $dist;
    }

    /**
     * Users aggregated by primary class
     */
    public function classDistribution(): array {
        $dist = self::$cache->get_value(self::USER_CLASS);
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT p.Name AS label,
                    count(*)  AS total
                FROM users_main AS um
                INNER JOIN permissions AS p ON (um.PermissionID = p.ID)
                WHERE um.Enabled = '1'
                GROUP BY label
                ORDER BY p.Level
            ");
            $dist = $this->reformatDist(self::$db->to_pair('label', 'total', false));
            self::$cache->cache_value(self::USER_CLASS, $dist, 86400);
        }
        return $dist;
    }

    /**
     * Users aggregated by OS platform
     */
    public function platformDistribution(): array {
        $dist = self::$cache->get_value(self::USER_PLATFORM);
        $dist = false;
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT OperatingSystem AS label,
                    count(*) AS total
                FROM users_sessions
                GROUP BY label
                ORDER BY total DESC
            ");
            $dist = $this->reformatDist(self::$db->to_pair('label', 'total', false));
            self::$cache->cache_value(self::USER_PLATFORM, $dist, 86400);
        }
        return $dist;
    }

    /**
     * Country aggregates.
     * TODO: this is really fucked
     */
    public function geodistribution(): array {
        if (![$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements] = self::$cache->get_value('geodistribution')) {
            self::$db->prepared_query("
                SELECT Code, Users FROM users_geodistribution
            ");
            $Data = self::$db->to_array();
            $Count = self::$db->record_count() - 1;

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
            self::$cache->cache_value('geodistribution', [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements], 86400 * 3);
        }
        return [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements];
    }

    public function frontPage(): int {
        self::$cache->cache_value('stats_snatches',
            (int)self::$db->scalar("SELECT count(*) FROM xbt_snatched"),
            0
        );
        self::$cache->cache_value('stats_peers', [
                (int)self::$db->scalar("SELECT count(*) FROM xbt_files_users WHERE active = 1 AND remaining = 0"),
                (int)self::$db->scalar("SELECT count(*) FROM xbt_files_users WHERE active = 1 AND remaining > 0"),
            ], 0
        );
        return self::$cache->get_value('stats_peers')[1];
    }

    /**
     * Get the number of enabled users last day/week/month
     *
     * @return array [Day, Week, Month]
     */
    public function globalActivityStats(): array {
        $stats = self::$cache->get_value('stats_users');
        if ($stats === false) {
            $stats = array_map(fn($n) => (int)$n,
                self::$db->rowAssoc("
                    SELECT
                        sum(ula.last_access > now() - INTERVAL 1 DAY) AS Day,
                        sum(ula.last_access > now() - INTERVAL 1 WEEK) AS Week,
                        sum(ula.last_access > now() - INTERVAL 1 MONTH) AS Month
                    FROM users_main um
                    INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
                    WHERE um.Enabled = '1'
                        AND ula.last_access > now() - INTERVAL 1 MONTH
                ")
            );
            self::$cache->cache_value('stats_users', $stats, 7200);
        }
        return $stats;
    }

    public function refresh(): int {
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE user_summary_new LIKE user_summary
        ");

        /* Need to perform dirty reads to avoid wedging users, especially inserts to users_downloads */
        self::$db->prepared_query("
            SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, artist_added_total)
                SELECT ta.UserID, count(*)
                FROM torrents_artists ta
                GROUP BY ta.UserID
            ON DUPLICATE KEY UPDATE
                artist_added_total = VALUES(artist_added_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, collage_total)
                SELECT c.UserID, count(*)
                FROM collages c
                WHERE c.Deleted = '0'
                GROUP BY c.UserID
            ON DUPLICATE KEY UPDATE
                collage_total = VALUES(collage_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, collage_contrib)
                SELECT ct.UserID, count(*)
                FROM collages c
                INNER JOIN collages_torrents ct ON (ct.CollageID = c.ID)
                WHERE c.Deleted = '0'
                GROUP BY ct.UserID
            ON DUPLICATE KEY UPDATE
                collage_contrib = collage_contrib + VALUES(collage_contrib)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, collage_contrib)
                SELECT ca.UserID, count(*)
                FROM collages c
                INNER JOIN collages_artists ca ON (ca.CollageID = c.ID)
                WHERE c.Deleted = '0'
                GROUP BY ca.UserID
            ON DUPLICATE KEY UPDATE
                collage_contrib = collage_contrib + VALUES(collage_contrib)
        ");

        self::$db->prepared_query("
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

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, fl_token_total)
                SELECT uf.UserID, count(*) AS fl_token_total
                FROM users_freeleeches uf
                GROUP BY uf.UserID
            ON DUPLICATE KEY UPDATE
                fl_token_total = VALUES(fl_token_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, forum_post_total)
                SELECT fp.AuthorID, count(*) AS forum_post_total
                FROM forums_posts fp
                GROUP BY fp.AuthorID
            ON DUPLICATE KEY UPDATE
                forum_post_total = VALUES(forum_post_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, forum_thread_total)
                SELECT ft.AuthorID, count(*) AS forum_thread_total
                FROM forums_topics ft
                GROUP BY ft.AuthorID
            ON DUPLICATE KEY UPDATE
                forum_thread_total = VALUES(forum_thread_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, invited_total)
                SELECT ui.Inviter, count(*) AS invited_total
                FROM users_info ui
                WHERE ui.Inviter IS NOT NULL
                GROUP BY ui.Inviter
            ON DUPLICATE KEY UPDATE
                invited_total = VALUES(invited_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, unique_group_total, upload_total)
                SELECT t.UserID,
                    count(DISTINCT GroupID) AS unique_group_total,
                    count(*) AS upload_total
                FROM torrents t
                GROUP BY t.UserID
            ON DUPLICATE KEY UPDATE
                unique_group_total = VALUES(unique_group_total),
                upload_total = VALUES(upload_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, perfect_flac_total)
                SELECT t.UserID, count(DISTINCT t.GroupID) AS perfect_flac_total
                FROM torrents t
                WHERE t.Format = 'FLAC'
                    AND (
                        (t.Media = 'CD' AND t.LogScore = 100)
                        OR (t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'))
                    )
                GROUP BY t.UserID
            ON DUPLICATE KEY UPDATE
                perfect_flac_total = VALUES(perfect_flac_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, perfecter_flac_total)
                SELECT t.UserID, count(DISTINCT t.GroupID) AS perfecter_flac_total
                FROM torrents t
                WHERE t.Format = 'FLAC'
                    AND (
                        (t.Media = 'CD' AND t.LogScore = 100)
                        OR (t.Media IN ('Vinyl', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'))
                    )
                GROUP BY t.UserID
            ON DUPLICATE KEY UPDATE
                perfecter_flac_total = VALUES(perfecter_flac_total)
        ");

        self::$db->prepared_query("
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

        self::$db->prepared_query("
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

        /**
         * Note: exclude the bounty voted by a user on a request they filled themselves,
         * as that increase has already been accounted for in users_leech_stats.Uploaded
         */
        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, request_vote_size, request_vote_total)
                SELECT rv.UserID,
                    coalesce(sum(rv.Bounty), 0) AS size,
                    count(*) AS total
                FROM requests_votes rv
                INNER JOIN requests r ON (r.ID = rv.RequestID)
                WHERE r.UserID != r.FillerID
                GROUP BY rv.UserID
            ON DUPLICATE KEY UPDATE
                request_vote_size = VALUES(request_vote_size),
                request_vote_total = VALUES(request_vote_total)
        ");

        self::$db->prepared_query("
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

        self::$db->prepared_query("
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

        self::$db->prepared_query("
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

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM user_summary
        ");
        self::$db->prepared_query("
            INSERT INTO user_summary
            SELECT * FROM user_summary_new
        ");
        $processed = self::$db->affected_rows();
        self::$db->commit();
        return $processed;
    }

    public function registerActivity(string $tableName, int $days): int {
        if ($days > 0) {
            self::$db->prepared_query("
                 DELETE FROM $tableName WHERE Time < now() - INTERVAL ? DAY
                 ", $days
            );
        }
        self::$db->prepared_query("
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
        return self::$db->affected_rows();
    }

    public function browserList(): array {
        self::$db->prepared_query("
            SELECT Browser     AS name,
                BrowserVersion AS `version`,
                count(*)       AS total
            FROM users_sessions
            WHERE Browser IS NOT NULL
            GROUP BY name, version
            ORDER BY total DESC, name, version
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function operatingSystemList(): array {
        self::$db->prepared_query("
            SELECT OperatingSystem     AS name,
                OperatingSystemVersion AS version,
                count(*)               AS total
            FROM users_sessions
            WHERE OperatingSystem IS NOT NULL
            GROUP BY name, version
            ORDER BY total DESC, name, version
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Get the number of enabled users.
     *
     * @return int Number of enabled users (this is cached).
     */
    public function enabledUserTotal(): int {
        $total = self::$cache->get_value('stats_user_count');
        if ($total === false) {
            $total = self::$db->scalar("
                SELECT count(*) FROM users_main WHERE Enabled = '1'
            ");
            self::$cache->cache_value('stats_user_count', $total, 7200);
        }
        return $total;
    }

    /**
     * Can new members be invited at this time?
     * @return bool Yes we can
     */
    public function newUsersAllowed(\Gazelle\User $user): bool {
        return $user->canInvite()
            && (
                   USER_LIMIT === 0
                || $this->enabledUserTotal() < USER_LIMIT
                || $user->permitted('site_can_invite_always')
            );
    }
}
