<?php

namespace Gazelle\Stats;

use Gazelle\Enum\UserStatus;

class Users extends \Gazelle\Base {
    protected const USER_BROWSER  = 'stat_u_browser';
    protected const USER_CLASS    = 'stat_u_class';
    protected const USER_PLATFORM = 'stat_u_platform';
    protected const FLOW          = 'stat_flow';

    protected array|null $info;

    public function flush(): static {
        self::$cache->deleteMulti([
            self::USER_BROWSER,
            self::USER_CLASS,
            self::USER_PLATFORM,
            self::FLOW,
        ]);
        unset($this->info);
        return $this;
    }

    public function flushTop(int $limit): static {
        self::$cache->deleteMulti([
            "topuserdl_$limit",
            "topuserds_$limit",
            "topuserul_$limit",
            "topuserus_$limit",
            "topusertotup_$limit",
        ]);
        return $this;
    }

    /**
     * The annual flow of users: people registered and disabled
     */
    public function flow(): array {
        $flow = self::$cache->get_value(self::FLOW);
        if ($flow === false) {
            /* Mysql does not implement a full outer join, so if there is a month with
             * no joiners, any banned users in that same month will not appear.
             * Mysql does not implement sequence generators as in Postgres, so if there
             * is a month without any joiners or banned, it will not appear at all.
             * Deal with it. - Spine
             */
            self::$db->prepared_query("
                SELECT J.Mon,
                    J.n              AS new,
                    coalesce(D.n, 0) AS disabled
                FROM (
                    SELECT DATE_FORMAT(created,'%Y%m') AS M,
                        DATE_FORMAT(created, '%b %Y')  AS Mon,
                        count(*)                       AS n
                    FROM users_main
                    GROUP BY M
                    ORDER BY 1 DESC
                    LIMIT 1, 12
                    ) J
                LEFT JOIN (
                    SELECT DATE_FORMAT(BanDate, '%Y%m') AS M,
                        DATE_FORMAT(BanDate, '%b %Y')   AS Mon,
                        count(*)                        AS n
                    FROM users_info
                    GROUP By M
                    ORDER BY 1 DESC
                    LIMIT 1, 12
                ) D USING (M)
                ORDER BY J.M;
            ");
            $flow = self::$db->to_array('Mon', MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::FLOW, $flow, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for Dec -> Jan
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
    public function browserDistributionList(): array {
        $dist = self::$cache->get_value(self::USER_BROWSER);
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT Browser AS label,
                    count(*) AS total
                FROM users_sessions
                GROUP BY label
                ORDER BY total DESC
            ");
            $dist = self::$db->to_pair('label', 'total', false);
            self::$cache->cache_value(self::USER_BROWSER, $dist, 86400);
        }
        return $dist;
    }

    public function browserDistribution(): array {
        return $this->reformatDist($this->browserDistributionList());
    }

    /**
     * Users aggregated by primary class
     */
    public function userclassDistributionList(): array {
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
            $dist = self::$db->to_pair('label', 'total', false);
            self::$cache->cache_value(self::USER_CLASS, $dist, 86400);
        }
        return $dist;
    }

    public function userclassDistribution(): array {
        return $this->reformatDist($this->userclassDistributionList());
    }

    /**
     * Users aggregated by OS platform
     */
    public function platformDistributionList(): array {
        $dist = self::$cache->get_value(self::USER_PLATFORM);
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT OperatingSystem AS label,
                    count(*) AS total
                FROM users_sessions
                GROUP BY label
                ORDER BY total DESC
            ");
            $dist = self::$db->to_pair('label', 'total', false);
            self::$cache->cache_value(self::USER_PLATFORM, $dist, 86400);
        }
        return $dist;
    }

    public function platformDistribution(): array {
        return $this->reformatDist($this->platformDistributionList());
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
            $Count = (int)self::$db->record_count() - 1;

            if ($Count < 30) {
                $CountryMinThreshold = $Count;
            } else {
                $CountryMinThreshold = 30;
            }

            $CountryMax = ceil(log(max(1, $Data[0][1])) / log(2)) + 1;
            $CountryMin = floor(log(max(1, $Data[$CountryMinThreshold][1])) / log(2));

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
                $LogIncrements[] = human_format(2 ** $i);
            }
            self::$cache->cache_value('geodistribution', [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements], 86400 * 3);
        }
        return [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements];
    }

    public function peerStat(): array {
        if (!isset($this->info)) {
            $this->info = [];
        }
        if (!isset($this->info['xbt_files_users'])) {
            $stat = self::$cache->get_value('stat_xbt_fu');
            if ($stat === false) {
                $stat = array_map('intval',
                    self::$db->rowAssoc("
                        SELECT count(*)                  AS peer_total,
                            sum(if(remaining = 0, 1, 0)) AS seeder_total,
                            sum(if(remaining > 0, 1, 0)) AS leecher_total
                        FROM xbt_files_users
                        WHERE active = 1
                    ") ?? ['seeder_total' => 0, 'leecher_total' => 0]
                );
                self::$cache->cache_value('stat_xbt_fu', $stat, 3600 + random_int(0, 120));
            }
            $this->info['xbt_files_users'] = $stat;
        }
        return $this->info['xbt_files_users'];
    }

    public function leecherTotal(): int {
        return $this->peerStat()['leecher_total'];
    }

    public function peerTotal(): int {
        return $this->peerStat()['peer_total'];
    }

    public function seederTotal(): int {
        return $this->peerStat()['seeder_total'];
    }

    public function snatchTotal(): int {
        if (!isset($this->info)) {
            $this->info = [];
        }
        if (!isset($this->info['snatch'])) {
            $total = self::$cache->get_value('stats_snatch');
            if ($total === false) {
                $total = (int)self::$db->scalar("SELECT count(*) FROM xbt_snatched");
                self::$cache->cache_value('stats_snatch', $total, 3600 + random_int(0, 12));
            }
            $this->info['snatch'] = $total;
        }
        return $this->info['snatch'];
    }

    /**
     * Get the number of enabled users.
     *
     * @return int Number of enabled users (this is cached).
     */
    public function enabledUserTotal(): int {
        if (!isset($this->info)) {
            $this->info = [];
        }
        if (!isset($this->info['enabled'])) {
            $total = self::$cache->get_value('stats_user_count');
            if ($total === false) {
                $total = (int)self::$db->scalar("
                    SELECT count(*) FROM users_main WHERE Enabled = '1'
                ");
                self::$cache->cache_value('stats_user_count', $total, 7200);
            }
            $this->info['enabled'] = $total;
        }
        return $this->info['enabled'];
    }

    /**
     * Can new members be invited at this time?
     */
    public function newUsersAllowed(\Gazelle\User $user): bool {
        return (
               USER_LIMIT == 0
            || $this->enabledUserTotal() < USER_LIMIT
            || $user->permitted('site_can_invite_always')
        );
    }

    public function activityStat(): array {
        if (!isset($this->info)) {
            $this->info = [];
        }
        if (!isset($this->info['active'])) {
            $active = self::$cache->get_value('stats_user_active');
            if ($active === false) {
                $active = array_map('intval',
                    self::$db->rowAssoc("
                        SELECT
                            sum(ula.last_access > now() - INTERVAL 1 DAY)   AS active_day,
                            sum(ula.last_access > now() - INTERVAL 1 WEEK)  AS active_week,
                            sum(ula.last_access > now() - INTERVAL 1 MONTH) AS active_month
                        FROM users_main um
                        INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
                        WHERE um.Enabled = '1'
                            AND ula.last_access > now() - INTERVAL 1 MONTH
                    ") ?? ['active_day' => 0, 'active_week' => 0, 'active_month' => 0]
                );
                self::$cache->cache_value('stats_user_active', $active, 7200 + random_int(0, 300));
            }
            $this->info['active'] = $active;
        }
        return $this->info['active'];
    }

    public function dayActiveTotal(): int {
        return $this->activityStat()['active_day'];
    }

    public function weekActiveTotal(): int {
        return $this->activityStat()['active_week'];
    }

    public function monthActiveTotal(): int {
        return $this->activityStat()['active_month'];
    }

    public function stockpileTokenList(int $limit): array {
        self::$db->prepared_query("
            SELECT user_id,
                tokens AS total
            FROM user_flt
            ORDER BY tokens DESC
            LIMIT ?
            ", $limit
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function refresh(): int {
        self::$db->dropTemporaryTable("user_summary_new");
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
                INNER JOIN users_main um ON (um.ID = ta.UserID)
                GROUP BY ta.UserID
            ON DUPLICATE KEY UPDATE
                artist_added_total = VALUES(artist_added_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, collage_total)
                SELECT c.UserID, count(*)
                FROM collages c
                INNER JOIN users_main um ON (um.ID = c.UserID)
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
                INNER JOIN users_main um ON (um.ID = ct.UserID)
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
                INNER JOIN users_main um ON (um.ID = ca.UserID)
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
               INNER JOIN users_main um ON (um.ID = ud.UserID)
               GROUP BY ud.UserID
            ON DUPLICATE KEY UPDATE
                download_total = VALUES(download_total),
                download_unique = VALUES(download_unique)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, fl_token_total)
                SELECT uf.UserID, count(*) AS fl_token_total
                FROM users_freeleeches uf
                INNER JOIN users_main um ON (um.ID = uf.UserID)
                GROUP BY uf.UserID
            ON DUPLICATE KEY UPDATE
                fl_token_total = VALUES(fl_token_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, forum_post_total)
                SELECT fp.AuthorID, count(*) AS forum_post_total
                FROM forums_posts fp
                INNER JOIN users_main um ON (um.ID = fp.AuthorID)
                GROUP BY fp.AuthorID
            ON DUPLICATE KEY UPDATE
                forum_post_total = VALUES(forum_post_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, forum_thread_total)
                SELECT ft.AuthorID, count(*) AS forum_thread_total
                FROM forums_topics ft
                INNER JOIN users_main um ON (um.ID = ft.AuthorID)
                GROUP BY ft.AuthorID
            ON DUPLICATE KEY UPDATE
                forum_thread_total = VALUES(forum_thread_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, invited_total)
                SELECT um.inviter_user_id, count(*) AS invited_total
                FROM users_main um
                WHERE um.inviter_user_id > 0
                GROUP BY um.inviter_user_id
            ON DUPLICATE KEY UPDATE
                invited_total = VALUES(invited_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, unique_group_total, upload_total)
                SELECT t.UserID,
                    count(DISTINCT GroupID) AS unique_group_total,
                    count(*) AS upload_total
                FROM torrents t
                INNER JOIN users_main um ON (um.ID = t.UserID)
                GROUP BY t.UserID
            ON DUPLICATE KEY UPDATE
                unique_group_total = VALUES(unique_group_total),
                upload_total = VALUES(upload_total)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, perfect_flac_total)
                SELECT t.UserID, count(DISTINCT t.GroupID) AS perfect_flac_total
                FROM torrents t
                INNER JOIN users_main um ON (um.ID = t.UserID)
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
                INNER JOIN users_main um ON (um.ID = t.UserID)
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
                INNER JOIN users_main um ON (um.ID = r.FillerID)
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
                INNER JOIN users_main um ON (um.ID = r.UserID)
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
                INNER JOIN users_main um ON (um.ID = rv.UserID)
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
                INNER JOIN users_main um ON (um.ID = xfu.uid)
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
                INNER JOIN users_main um ON (um.ID = xfu.uid)
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
               INNER JOIN users_main um ON (um.ID = xs.uid)
               GROUP BY xs.uid
            ON DUPLICATE KEY UPDATE
                snatch_total = VALUES(snatch_total),
                snatch_unique = VALUES(snatch_unique)
        ");

        self::$db->prepared_query("
            INSERT INTO user_summary_new (user_id, seedtime_hour)
                SELECT xfh.uid,
                   sum(seedtime)
                FROM xbt_files_history xfh
                INNER JOIN users_main um ON (um.ID = xfh.uid)
                INNER JOIN torrents t ON (t.ID = xfh.fid)
                GROUP BY xfh.uid
            ON DUPLICATE KEY UPDATE
                seedtime_hour = VALUES(seedtime_hour)
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
        self::$db->dropTemporaryTable("user_summary_new");
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

    public function topDownloadList(int $limit): array {
        $key = "topuserdl_$limit";
        $top = self::$cache->get_value($key);
        if ($top === false) {
            self::$db->prepared_query("
                SELECT um.ID
                FROM users_main um
                INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                    AND (um.Paranoia IS NULL OR um.Paranoia NOT REGEXP 'downloaded')
                ORDER BY uls.Downloaded DESC
                LIMIT ?
                ", UserStatus::enabled->value, $limit
            );
            $top = self::$db->collect(0, false);
            self::$cache->cache_value($key, $top, 3600 * 12);
        }
        return $top;
    }

    public function topDownSpeedList(int $limit): array {
        $key = "topuserds_$limit";
        $top = self::$cache->get_value($key);
        if ($top === false) {
            self::$db->prepared_query("
                SELECT um.ID
                FROM users_main um
                INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                    AND (um.Paranoia IS NULL OR um.Paranoia NOT REGEXP 'downloaded')
                ORDER BY uls.Downloaded / (unix_timestamp(now()) - unix_timestamp(um.created)) DESC
                LIMIT ?
                ", UserStatus::enabled->value, $limit
            );
            $top = self::$db->collect(0, false);
            self::$cache->cache_value($key, $top, 3600 * 12);
        }
        return $top;
    }

    public function topUploadList(int $limit): array {
        $key = "topuserul_$limit";
        $top = self::$cache->get_value($key);
        if ($top === false) {
            self::$db->prepared_query("
                SELECT um.ID
                FROM users_main um
                INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                    AND (um.Paranoia IS NULL OR um.Paranoia NOT REGEXP 'uploaded')
                ORDER BY uls.Uploaded DESC
                LIMIT ?
                ", UserStatus::enabled->value, $limit
            );
            $top = self::$db->collect(0, false);
            self::$cache->cache_value($key, $top, 3600 * 12);
        }
        return $top;
    }

    public function topUpSpeedList(int $limit): array {
        $key = "topuserus_$limit";
        $top = self::$cache->get_value($key);
        if ($top === false) {
            self::$db->prepared_query("
                SELECT um.ID
                FROM users_main um
                INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                    AND (um.Paranoia IS NULL OR um.Paranoia NOT REGEXP 'uploaded')
                ORDER BY uls.Uploaded / (unix_timestamp(now()) - unix_timestamp(um.created)) DESC
                LIMIT ?
                ", UserStatus::enabled->value, $limit
            );
            $top = self::$db->collect(0, false);
            self::$cache->cache_value($key, $top, 3600 * 12);
        }
        return $top;
    }

    public function topTotalUploadList(int $limit): array {
        $key = "topusertotup_$limit";
        $top = self::$cache->get_value($key);
        if ($top === false) {
            self::$db->prepared_query("
                SELECT um.ID
                FROM users_main um
                INNER JOIN user_summary us ON (us.user_id = um.ID)
                WHERE um.Enabled = ?
                    AND (um.Paranoia IS NULL OR um.Paranoia NOT REGEXP 'uploaded')
                ORDER BY us.upload_total DESC
                LIMIT ?
                ", UserStatus::enabled->value, $limit
            );
            $top = self::$db->collect(0, false);
            self::$cache->cache_value($key, $top, 3600 * 12);
        }
        return $top;
    }
}
