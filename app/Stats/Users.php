<?php

namespace Gazelle\Stats;

use Gazelle\Enum\UserStatus;

class Users extends \Gazelle\Base {
    protected const USER_BROWSER  = 'stat_u2_browser';
    protected const USER_CLASS    = 'stat_u2_class';
    protected const USER_GEODIST  = 'stat_u_geodist';
    protected const USER_PLATFORM = 'stat_u2_platform';
    protected const FLOW          = 'stat_u_flow';

    protected array $info;

    public function flush(): static {
        self::$cache->deleteMulti([
            self::USER_BROWSER,
            self::USER_CLASS,
            self::USER_GEODIST,
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
            self::$db->prepared_query("
                WITH RECURSIVE dates AS (
                    SELECT last_day(now() - INTERVAL 24 MONTH) AS eom
                    UNION ALL
                    SELECT last_day(eom + INTERVAL 1 MONTH)
                    FROM dates
                    WHERE last_day(eom + INTERVAL 1 MONTH) < last_day(now())
                ),
                unew AS (
                    SELECT count(*)         AS total,
                        last_day(u.created) AS eom
                    FROM users_main u
                    WHERE last_day(u.created)
                        BETWEEN last_day(now() - INTERVAL 24 MONTH)
                        AND last_day(now() - INTERVAL 1 MONTH)
                    GROUP BY eom
                )
                SELECT date_format(dates.eom, '%Y-%m') AS Month,
                    coalesce(unew.total, 0)            AS new,
                    count(distinct ui.UserID)          AS disabled
                FROM dates
                LEFT JOIN unew USING (eom)
                LEFT JOIN users_info ui ON (last_day(ui.BanDate) = dates.eom)
                GROUP BY dates.eom
                ORDER BY dates.eom
            ");
            $flow = self::$db->to_array('Month', MYSQLI_ASSOC);
            foreach ($flow as &$f) {
                $f['new'] = (int)$f['new'];
            }
            unset($f);
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
    public function browserDistributionList(bool $viewAll): array {
        $dist = self::$cache->get_value(self::USER_BROWSER);
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT coalesce(Browser, 'unreported') AS label,
                    count(*) AS total
                FROM users_sessions
                GROUP BY label
                ORDER BY total DESC
            ");
            $dist = self::$db->to_pair('label', 'total');
            self::$cache->cache_value(self::USER_BROWSER, $dist, 86400);
        }
        $result = [];
        // Users see up to the 10 most popular browsers with totals rounded up
        // to the nearest 10. Staff see everything.
        foreach ($dist as $label => $total) {
            if ($viewAll) {
                $result[$label] = $total;
            } elseif ($label === 'staff-browser') {
                continue;
            } elseif ($total > 10) {
                $result[$label] = (int)(ceil($total / 10) * 10);
                if (count($result) >= 10) {
                    break;
                }
            }
        }
        return $result;
    }

    public function browserDistribution($viewAll): array {
        return $this->reformatDist($this->browserDistributionList($viewAll));
    }

    /**
     * Users aggregated by primary class
     */
    public function userclassDistributionList(bool $viewAll): array {
        $dist = self::$cache->get_value(self::USER_CLASS);
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT p.Name AS label,
                    p.Level AS level,
                    count(um.ID) AS total
                FROM permissions p
                LEFT JOIN users_main um ON (um.PermissionID = p.ID)
                WHERE p.Secondary = 0
                    AND (um.ID IS NULL OR um.Enabled = ?)
                GROUP BY p.Name, p.Level
                ORDER BY p.Level
                ", UserStatus::enabled->value
            );
            $dist = self::$db->to_array(false, MYSQLI_ASSOC);
            self::$cache->cache_value(self::USER_CLASS, $dist, 86400);
        }
        $result = [];
        // Users only see non-staff userclasses (the staff classes can be
        // worked out by consulting the Staff roster page in any event).
        // Staff see all userclasses.
        foreach ($dist as $d) {
            if (
                $viewAll
                || ($d['level'] >= CLASSLEVEL_USER && $d['level'] < CLASSLEVEL_STAFF)
            ) {
                $result[$d['label']] = $d['total'];
            }
        }
        return $result;
    }

    public function userclassDistribution(bool $viewAll): array {
        return $this->reformatDist($this->userclassDistributionList($viewAll));
    }

    /**
     * Users aggregated by OS platform
     */
    public function platformDistributionList(bool $viewAll): array {
        $dist = self::$cache->get_value(self::USER_PLATFORM);
        if ($dist === false) {
            self::$db->prepared_query("
                SELECT coalesce(OperatingSystem, 'unreported') AS label,
                    count(*) AS total
                FROM users_sessions
                GROUP BY label
                ORDER BY total DESC
            ");
            $dist = self::$db->to_array(false, MYSQLI_ASSOC);
            self::$cache->cache_value(self::USER_PLATFORM, $dist, 86400);
        }
        $result = [];
        // Users see up to the 10 most popular platforms with totals rounded up
        // to the nearest 10. Staff see everything.
        foreach ($dist as $d) {
            if ($viewAll) {
                $result[$d['label']] = $d['total'];
            } elseif ($d['total'] > 10) {
                $result[$d['label']] = (int)(ceil($d['total'] / 10) * 10);
            }
        }
        return $result;
    }

    public function platformDistribution(bool $viewAll): array {
        return $this->reformatDist($this->platformDistributionList($viewAll));
    }

    /**
     * Country aggregates. For privacy, countries with less than 20 members
     * are rounded up to 20, then increases by increments of 10.
     * Precise numbers are made available for consumption by staff.
     * If the $iso array is supplied, it is assumed to be a lookup
     * table for ISO31660-1 to country name.
     */
    public function geodistribution(array $iso = []): array {
        $info = self::$cache->get_value(self::USER_GEODIST);
        if ($info === false) {
            self::$db->prepared_query("
                WITH geo AS (
                    SELECT ipcc,
                        count(*)              AS total,
                        greatest(count(*), ?) AS rounded
                    FROM users_main
                    GROUP BY ipcc
                    ORDER BY total
                )
                SELECT geo.ipcc,
                    geo.total                              AS staff,
                    ceil(rounded / ?) * ?                  AS public,
                    ntile(100) OVER (ORDER BY rounded ASC) AS n
                FROM geo
                ORDER BY n DESC,
                    public DESC,
                    staff DESC,
                    ipcc
                ", COUNTRY_MINIMUM, COUNTRY_STEP, COUNTRY_STEP
            );
            $info = [];
            foreach (self::$db->to_array(false, MYSQLI_ASSOC) as $row) {
                $row['public']  = (int)$row['public']; // ceil() returns a float
                $row['country'] = isset($iso[$row['ipcc']]) ? "{$iso[$row['ipcc']]} [{$row['ipcc']}]" : "[{$row['ipcc']}]";
                $info[]         = $row;
            }
            self::$cache->cache_value(self::USER_GEODIST, $info, 86400 * 2);
        }
        return $info;
    }

    public function geodistributionChart(\Gazelle\User $user): array {
        return array_map(
            fn ($c) => [
                'ipcc'  => $c['ipcc'],
                'name'  => $c['country'],
                'value' => $user->isStaff() ? $c['staff'] : $c['public'],
            ],
            $this->geodistribution(ISO3166_2()),
        );
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
                    SELECT count(*) FROM users_main WHERE Enabled = ?
                    ", UserStatus::enabled->value
                );
                self::$cache->cache_value('stats_user_count', $total, 7200);
            }
            $this->info['enabled'] = $total;
        }
        return $this->info['enabled'];
    }

    public function overUsercap(): bool {
        return USER_LIMIT > 0
            && $this->enabledUserTotal() > USER_LIMIT;
    }

    /**
     * Can new members be invited at this time?
     */
    public function newUsersAllowed(\Gazelle\User $user): bool {
        return !$this->overUsercap()
            || $user->permitted('site_can_invite_always');
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
                        WHERE um.Enabled = ?
                            AND ula.last_access > now() - INTERVAL 1 MONTH
                        ", UserStatus::enabled->value
                    )
                    ?? ['active_day' => 0, 'active_week' => 0, 'active_month' => 0]
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
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function refresh(): int {
        /* Postgres cannot see Mysql temporary tables, so the temp table is
         * created in Postgres and the results copied over.
         */
        $this->pg()->query("
            create temporary table user_summary_new (like relay.user_summary)
        ");
        /* Default values are not copied over from a foreign table, so we have
         * to add them manually.
         */
        $sql = $this->pg()->scalarex("
            with c as (
                select column_name
                from information_schema.columns
                where table_name = 'user_summary_new'
                    and column_name != 'user_id'
            )
            select 'alter table user_summary_new '
                || string_agg(
                    'alter column ' || column_name || ' set default 0',
                    ', '
                )
            from c
        ");
        $this->pg()->query($sql);
        $this->pg()->query("alter table user_summary_new add primary key (user_id)");

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, artist_added_total)
                select ta."UserID",
                    count(*)
                from relay.torrents_artists ta
                inner join relay.users_main um on (um."ID" = ta."UserID")
                group by ta."UserID"
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, collage_total, collage_contrib)
                with c as (
                    select c."UserID" as user_id,
                        count(c."ID") as total
                    from relay.collages c
                    where c."Deleted" = '0'
                    group by c."UserID"
                ),
                ca as (
                    select ca."UserID"                as user_id,
                        count(distinct ca."ArtistID") as total
                    from relay.collages_artists ca
                    inner join relay.collages c on (c."ID" = ca."CollageID" and c."Deleted" = '0')
                    group by ca."UserID"
                ),
                ct as (
                    select ct."UserID"               as user_id,
                        count(distinct ct."GroupID") as total
                    from relay.collages_torrents ct
                    inner join relay.collages c on (c."ID" = ct."CollageID" and c."Deleted" = '0')
                    group by ct."UserID"
                )
                select um."ID",
                    coalesce(c.total, 0)                          as collage_total,
                    coalesce(ca.total, 0) + coalesce(ct.total, 0) as collage_contrib
                from relay.users_main um
                left join c  on (c.user_id  = um."ID")
                left join ca on (ca.user_id = um."ID")
                left join ct on (ct.user_id = um."ID")
            on conflict (user_id) do update set
                collage_total   = EXCLUDED.collage_total,
                collage_contrib = EXCLUDED.collage_contrib
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, download_total, download_unique)
                select ud."UserID",
                   count(*)                       as download_total,
                   count(distinct ud."TorrentID") as download_unique
               from relay.users_downloads  ud
               inner join relay.torrents   t  on (t."ID" = ud."TorrentID")
               inner join relay.users_main um on (um."ID" = ud."UserID")
               group by ud."UserID"
            on conflict (user_id) do update set
                download_total  = EXCLUDED.download_total,
                download_unique = EXCLUDED.download_unique
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, fl_token_total)
                select uf."UserID",
                    count(*) as fl_token_total
                from relay.users_freeleeches uf
                inner join relay.users_main  um on (um."ID" = uf."UserID")
                group by uf."UserID"
            on conflict (user_id) do update set
                fl_token_total = EXCLUDED.fl_token_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, forum_post_total)
                select fp."AuthorID",
                    count(*) as forum_post_total
                from relay.forums_posts     fp
                inner join relay.users_main um on (um."ID" = fp."AuthorID")
                group by fp."AuthorID"
            on conflict (user_id) do update set
                forum_post_total = EXCLUDED.forum_post_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, forum_thread_total)
                select ft."AuthorID",
                    count(*) as forum_thread_total
                from relay.forums_topics    ft
                inner join relay.users_main um on (um."ID" = ft."AuthorID")
                group by ft."AuthorID"
            on conflict (user_id) do update set
                forum_thread_total = EXCLUDED.forum_thread_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, invited_total)
                select um.inviter_user_id,
                    count(*) as invited_total
                from relay.users_main um
                where um.inviter_user_id > 0
                group by um.inviter_user_id
            on conflict (user_id) do update set
                invited_total = EXCLUDED.invited_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, unique_group_total, upload_total)
                select t."UserID",
                    count(distinct t."GroupID") as unique_group_total,
                    count(*)                    as upload_total
                from relay.torrents         t
                inner join relay.users_main um on (um."ID" = t."UserID")
                group by t."UserID"
            on conflict (user_id) do update set
                unique_group_total = EXCLUDED.unique_group_total,
                upload_total       = EXCLUDED.upload_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, perfect_flac_total)
                select t."UserID",
                    count(distinct t."GroupID") as perfect_flac_total
                from relay.torrents         t
                inner join relay.users_main um on (um."ID" = t."UserID")
                where t."Format" = 'FLAC'
                    and (
                        (t."Media" = 'CD' and t."LogScore" = 100)
                        or (t."Media" in ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'))
                    )
                group by t."UserID"
            on conflict (user_id) do update set
                perfect_flac_total = EXCLUDED.perfect_flac_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, perfecter_flac_total)
                select t."UserID", count(distinct t."GroupID") as perfecter_flac_total
                from relay.torrents         t
                inner join relay.users_main um on (um."ID" = t."UserID")
                where t."Format" = 'FLAC'
                    and (
                        (t."Media" = 'CD' and t."LogScore" = 100)
                        or (t."Media" in ('Vinyl', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'))
                    )
                group by t."UserID"
            on conflict (user_id) do update set
                perfecter_flac_total = EXCLUDED.perfecter_flac_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, request_created_size, request_created_total, request_bounty_size, request_bounty_total, request_vote_size, request_vote_total)
                with crea as (
                    select r.id_user,
                        count(*)       as crea_total,
                        sum(rv.bounty) as crea_bounty
                    from request r
                    inner join request_vote rv using (id_request, id_user)
                    group by r.id_user
                ),
                filler as (
                    select r.id_filler as id_user,
                        count(distinct r.id_request) as fill_total,
                        /**
                         * Note: exclude the bounty voted by a user on a request they filled themselves,
                         * as that increase has already been accounted for in users_leech_stats.Uploaded
                         */
                        sum(case when rv.id_user = r.id_filler then 0 else rv.bounty end) as fill_bounty
                    from request            r
                    inner join request_vote rv using (id_request)
                    where r.filled is not null
                    group by r.id_filler
                ),
                voter as (
                    select rv.id_user,
                        count(distinct rv.id_request) as vote_total,
                        sum(rv.bounty) as vote_bounty
                    from request_vote rv
                    group by rv.id_user
                )
                select um."ID",
                    coalesce(crea_bounty,  0),
                    coalesce(crea_total,   0),
                    coalesce(fill_bounty,  0),
                    coalesce(fill_total,   0),
                    coalesce(vote_bounty,  0),
                    coalesce(vote_total,   0)
                from relay.users_main um
                left join crea   on (crea.id_user   = um."ID")
                left join filler on (filler.id_user = um."ID")
                left join voter  on (voter.id_user  = um."ID")
                where (
                       crea_total is not null
                    or fill_total is not null
                    or vote_total is not null
                )
            on conflict (user_id) do update set
                request_created_size  = EXCLUDED.request_created_size,
                request_created_total = EXCLUDED.request_created_total,
                request_bounty_size   = EXCLUDED.request_bounty_size,
                request_bounty_total  = EXCLUDED.request_bounty_total,
                request_vote_size     = EXCLUDED.request_vote_size,
                request_vote_total    = EXCLUDED.request_vote_total
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, leech_total, seeding_total)
                select xfu.uid,
                    sum(case when xfu.remaining > 0 then 1 else 0 end) as leeching,
                    sum(case when xfu.remaining = 0 then 1 else 0 end) as seeding
                from relay.xbt_files_users  xfu
                inner join relay.torrents   t  on (t."ID" = xfu.fid)
                inner join relay.users_main um on (um."ID" = xfu.uid)
                where um."Enabled" = $1
                group by xfu.uid
            on conflict (user_id) do update set
                leech_total   = EXCLUDED.leech_total,
                seeding_total = EXCLUDED.seeding_total
            END_SQL
            , UserStatus::enabled->value
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, bp_hourly_accrual)
                select xfu.uid,
                    sum(category_bonus_accrual(t."Size", xfh.seedtime, tls."Seeders", c.bonus_scale))
                from (
                    select distinct uid, fid
                    from relay.xbt_files_users
                    where active = 1
                        and remaining = 0
                        and mtime > extract(epoch from now() - interval '1 hour')
                ) as xfu
                inner join relay.xbt_files_history    xfh using (uid, fid)
                inner join relay.torrents             t   on (t."ID"          = xfu.fid)
                inner join relay.torrents_leech_stats tls on (tls."TorrentID" = t."ID")
                inner join relay.torrents_group       tg  on (tg."ID"         = t."GroupID")
                inner join relay.category             c   on (c.category_id   = tg."CategoryID")
                inner join relay.users_main           um  on (um."ID"         = xfu.uid)
                where um."Enabled" = $1
                group by xfu.uid
            on conflict (user_id) do update set
                bp_hourly_accrual = EXCLUDED.bp_hourly_accrual
            END_SQL
            , UserStatus::enabled->value
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, snatch_total, snatch_unique)
                select xs.uid,
                   count(*)               as snatch_total,
                   count(distinct xs.fid) as snatch_unique
               from relay.xbt_snatched     xs
               inner join relay.torrents   t  on (t."ID"  = xs.fid)
               inner join relay.users_main um on (um."ID" = xs.uid)
               group by xs.uid
            on conflict (user_id) do update set
                snatch_total  = EXCLUDED.snatch_total,
                snatch_unique = EXCLUDED.snatch_unique
        END_SQL
        );

        $this->pg()->query(<<<END_SQL
            insert into user_summary_new (user_id, seedtime_hour)
                select xfh.uid,
                   sum(seedtime)
                from relay.xbt_files_history xfh
                inner join relay.torrents    t  on (t."ID"  = xfh.fid)
                inner join relay.users_main  um on (um."ID" = xfh.uid)
                group by xfh.uid
            on conflict (user_id) do update set
                seedtime_hour = EXCLUDED.seedtime_hour
        END_SQL
        );

        // Transactions don't work across foreign tables, so we just have to wing it.
        $this->pg()->query("
            delete from relay.user_summary
        ");
        $processed = $this->pg()->query("
            insert into relay.user_summary
            select * from user_summary_new
        ");
        $this->pg()->query("
            drop table user_summary_new
        ");
        return $processed;
    }

    public function refreshUseragentTracker(): int {
        // NB: useragents may be null if the initial announce from ocelot
        // was lost, e.g. because of a deadlock or queue overflow.
        $result =  $this->pg()->execute("
            merge into history_useragent_tracker hut using (
                select xfu.uid as id_user,
                    coalesce(xfu.useragent, '') as useragent,
                    count(*) as total
                from relay.xbt_files_users xfu
                group by xfu.uid, xfu.useragent
            ) as i on hut.id_user = i.id_user
                and hut.useragent = i.useragent
            when not matched and i.total > 0 then
                insert (  id_user,   useragent,   total)
                values (i.id_user, i.useragent, i.total)
            when matched and i.total > 0 then
                update set
                    total = i.total
            when matched then
                delete
        ");
        return $result->getAffectedRows();
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
        return self::$db->to_array(false, MYSQLI_ASSOC);
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
        return self::$db->to_array(false, MYSQLI_ASSOC);
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
            $top = self::$db->collect(0);
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
            $top = self::$db->collect(0);
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
            $top = self::$db->collect(0);
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
            $top = self::$db->collect(0);
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
            $top = self::$db->collect(0);
            self::$cache->cache_value($key, $top, 3600 * 12);
        }
        return $top;
    }

    public function topTotalDownloadList(int $top, int $interval): array {
        self::$db->prepared_query("
            SELECT count(*) AS total,
                ud.UserID AS user_id,
                CASE WHEN prl.permission_id IS NOT NULL THEN 1 ELSE 0 END as limited,
                CASE WHEN um.Enabled = ? THEN 1 ELSE 0 END as disabled
            FROM users_downloads ud
            INNER JOIN users_main um ON (um.ID = ud.UserID)
            LEFT JOIN permission_rate_limit prl ON (prl.permission_id = um.PermissionID)
            WHERE ud.Time >= now() - INTERVAL ? DAY
            GROUP BY user_id, limited
            ORDER BY total DESC, ud.UserID
            LIMIT ?
            ", UserStatus::disabled->value, $interval, $top
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function recruitmentHistory(): array {
        return $this->pg()->all(<<<END_SQL
            select * from crosstab($$
            with r as (
                select isrc.name,
                to_char(um.created, 'YYYY-MM') as yyyymm,
                concat(
                    sum(case when um."Enabled" = '1' then 1 else 0 end)
                    || '/' ||
                    count(um."ID")
                ) as total
                from relay.users_main um
                inner join relay.user_has_invite_source uhis on (uhis.user_id = um."ID")
                inner join relay.invite_source isrc using (invite_source_id)
                where um.created > now() - interval '1 year'
                group by grouping sets ((isrc.name, yyyymm), (isrc.name))
                order by 1, 2
            )
            select name,
                coalesce(yyyymm, 'total'),
                nullif(total, '')
            from r
            $$,$$
            select to_char(
                 now() - interval '11 month' + (interval '1 month' * generate_series(0, 11)), 'YYYY-MM'
            )
            union select 'total'
            order by 1
            $$)
            as (
               name text,
               m11 text,
               m10 text,
               m09 text,
               m08 text,
               m07 text,
               m06 text,
               m05 text,
               m04 text,
               m03 text,
               m02 text,
               m01 text,
               m00 text,
               total text
            )
            END_SQL
        );
    }

    public function recruitmentHistoryRange(): array {
        return $this->pg()->column(<<<END_SQL
            select to_char(
                 now() - interval '11 month' + (interval '1 month' * generate_series(0, 11)), 'YYYY-MM'
            )
            union select 'total'
            order by 1
            END_SQL
        );
    }
}
