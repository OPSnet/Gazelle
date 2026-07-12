<?php

declare(strict_types=1);

namespace Gazelle\Stats;

use Gazelle\Enum\UserStatus;
use Gazelle\Enum\UserSummary;
use Gazelle\Task;

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

    public function refresh(Task|null $task = null): int {
        $processed = $this->pg()->query(<<<END_SQL
            merge into user_summary_fltoken usf
            using (
                with flrx(id_user, total) as (
                    select hb.id_user_other,
                        sum(bi.amount)
                    from history_bonus hb
                    inner join bonus_item bi using (id_bonus_item)
                    group by hb.id_user_other
                ),
                fltx(id_user, total) as (
                    select hb.id_user,
                        sum(bi.amount)
                    from history_bonus hb
                    inner join bonus_item bi using (id_bonus_item)
                    where hb.id_user_other is not null
                    group by hb.id_user
                ),
                flused(id_user, total) as (
                    select uf."UserID",
                        sum(uf."Uses")
                    from relay.users_freeleeches uf
                    group by uf."UserID"
                )
                select um."ID",
                    coalesce(flrx.total, 0),
                    coalesce(flrx.total, 0),
                    coalesce(flused.total, 0)
                from relay.users_main um
                left join flrx   on (flrx.id_user   = um."ID")
                left join fltx   on (fltx.id_user   = um."ID")
                left join flused on (flused.id_user = um."ID")
            ) as upd (id_user, gifted, received, used)
            on usf.id_user = upd.id_user
            when matched and (
                    usf.gifted   != upd.gifted
                 or usf.received != upd.received
                 or usf.used     != upd.used
            ) then update set
                gifted   = upd.gifted,
                received = upd.received,
                used     = upd.used
            when not matched then
                insert (    id_user,     gifted,     received,     used)
                values (upd.id_user, upd.gifted, upd.received, upd.used)
            when not matched by source then
                delete
            END_SQL
        );
        $task?->info("user_summary_fltoken", $processed);

        $processed += $this->pg()->query(<<<END_SQL
            merge into user_summary_misc usm
            using (
                with c(id_user, total) as (
                    select c."UserID",
                        count(c."ID")
                    from relay.collages c
                    where c."Deleted" = '0'
                    group by c."UserID"
                ),
                ca(id_user, total) as (
                    select ca."UserID",
                        count(distinct ca."ArtistID")
                    from relay.collages_artists ca
                    inner join relay.collages c on (c."ID" = ca."CollageID" and c."Deleted" = '0')
                    group by ca."UserID"
                ),
                ct(id_user, total) as (
                    select ct."UserID",
                        count(distinct ct."GroupID")
                    from relay.collages_torrents ct
                    inner join relay.collages c on (c."ID" = ct."CollageID" and c."Deleted" = '0')
                    group by ct."UserID"
                ),
                fp(id_user, total) as (
                    select id_user, total
                    from relay.v_summary_forums_posts
                ),
                ft(id_user, total) as (
                    select id_user, total
                    from relay.v_summary_forums_topics
                ),
                iv(id_user, total) as (
                    select id_user, total
                    from relay.v_summary_user_inviter
                )
                select um."ID",
                    coalesce(c.total, 0),
                    coalesce(ca.total, 0) + coalesce(ct.total, 0),
                    coalesce(fp.total, 0),
                    coalesce(ft.total, 0),
                    coalesce(iv.total, 0)
                from relay.users_main um
                left join c  on (c.id_user  = um."ID")
                left join ca on (ca.id_user = um."ID")
                left join ct on (ct.id_user = um."ID")
                left join fp on (fp.id_user = um."ID")
                left join ft on (ft.id_user = um."ID")
                left join iv on (iv.id_user = um."ID")
            ) as upd (id_user, collage_created, collage_contrib, forum_post, forum_thread, invited)
            on usm.id_user = upd.id_user
            when matched and (
                    usm.collage_created != upd.collage_created
                 or usm.collage_contrib != upd.collage_contrib
                 or usm.forum_post      != upd.forum_post
                 or usm.forum_thread    != upd.forum_thread
                 or usm.invited         != upd.invited
            ) then update set
                collage_created = upd.collage_created,
                collage_contrib = upd.collage_contrib,
                forum_post      = upd.forum_post,
                forum_thread    = upd.forum_thread,
                invited         = upd.invited
            when not matched then
                insert (    id_user,     collage_created,     collage_contrib,     forum_post,     forum_thread,     invited)
                values (upd.id_user, upd.collage_created, upd.collage_contrib, upd.forum_post, upd.forum_thread, upd.invited)
            when not matched by source then
                delete
            END_SQL
        );
        $task?->info("user_summary_misc", $processed);

        $processed += $this->pg()->query(<<<END_SQL
            merge into user_summary_request usr
            using (
                with crea(id_user, total, size) as (
                    select r.id_user,
                        count(*),
                        sum(rv.bounty)
                    from request r
                    inner join request_vote rv using (id_request, id_user)
                    group by r.id_user
                ),
                filler(id_user, total, size) as (
                    select r.id_filler,
                        count(distinct r.id_request),
                        /**
                         * Note: exclude the bounty voted by a user on a request they filled themselves,
                         * as that increase has already been accounted for in users_leech_stats.Uploaded
                         */
                        sum(case when rv.id_user = r.id_filler then 0 else rv.bounty end)
                    from request            r
                    inner join request_vote rv using (id_request)
                    where r.filled is not null
                    group by r.id_filler
                ),
                voter(id_user, total, size) as (
                    select rv.id_user,
                        count(distinct rv.id_request),
                        sum(rv.bounty)
                    from request_vote rv
                    group by rv.id_user
                )
                select um."ID",
                    coalesce(crea.size,    0),
                    coalesce(crea.total,   0),
                    coalesce(filler.size,  0),
                    coalesce(filler.total, 0),
                    coalesce(voter.size,   0),
                    coalesce(voter.total,  0)
                from relay.users_main um
                left join crea   on (crea.id_user   = um."ID")
                left join filler on (filler.id_user = um."ID")
                left join voter  on (voter.id_user  = um."ID")
            ) as upd (id_user, created_size, created_total, bounty_size, bounty_total, vote_size, vote_total)
            on usr.id_user = upd.id_user
            when matched and (
                    usr.created_size  != upd.created_size
                 or usr.created_total != upd.created_total
                 or usr.bounty_size   != upd.bounty_size
                 or usr.bounty_total  != upd.bounty_total
                 or usr.vote_size     != upd.vote_size
                 or usr.vote_total    != upd.vote_total
            ) then update set
                created_size  = upd.created_size,
                created_total = upd.created_total,
                bounty_size   = upd.bounty_size,
                bounty_total  = upd.bounty_total,
                vote_size     = upd.vote_size,
                vote_total    = upd.vote_total
            when not matched then
                insert (    id_user,     created_size,     created_total,     bounty_size,     bounty_total,     vote_size,     vote_total)
                values (upd.id_user, upd.created_size, upd.created_total, upd.bounty_size, upd.bounty_total, upd.vote_size, upd.vote_total)
            when not matched by source then
                delete
            END_SQL
        );
        $task?->info("user_summary_request", $processed);

        $processed += $this->pg()->query(<<<END_SQL
            merge into user_summary_seed uss
            using (
                with bp(id_user, bp_hourly_accrual) as (
                    select id_user, bp_hourly_accrual
                    from relay.v_summary_seed_bp
                ),
                leech(id_user, leeching, seeding) as (
                    select id_user, leeching, seeding
                    from relay.v_summary_seed_seeding
                ),
                seed(id_user, seedtime_hour) as (
                    select id_user, total
                    from relay.v_summary_seed_seedtime
                ),
                snatch(id_user, snatch_total, snatch_unique) as (
                    select id_user, snatch_total, snatch_unique
                    from relay.v_summary_seed_snatch
                )
                select um."ID",
                    coalesce(leech.leeching,       0),
                    coalesce(leech.seeding,        0),
                    coalesce(snatch.snatch_total,  0),
                    coalesce(snatch.snatch_unique, 0),
                    coalesce(bp.bp_hourly_accrual, 0.0),
                    coalesce(seed.seedtime_hour,   0.0)
                from relay.users_main um
                left join bp     on (bp.id_user     = um."ID")
                left join leech  on (leech.id_user  = um."ID")
                left join seed   on (seed.id_user   = um."ID")
                left join snatch on (snatch.id_user = um."ID")
                where um."Enabled" = $1
            ) as upd (id_user, leeching, seeding, snatch_total, snatch_unique, bp_accrual_hour, seedtime_hour)
            on uss.id_user = upd.id_user
            when matched and (
                    uss.leeching        != upd.leeching
                 or uss.seeding         != upd.seeding
                 or uss.snatch_total    != upd.snatch_total
                 or uss.snatch_unique   != upd.snatch_unique
                 or uss.bp_accrual_hour != upd.bp_accrual_hour
                 or uss.seedtime_hour   != upd.seedtime_hour
            ) then update set
                leeching        = upd.leeching,
                seeding         = upd.seeding,
                snatch_total    = upd.snatch_total,
                snatch_unique   = upd.snatch_unique,
                bp_accrual_hour = upd.bp_accrual_hour,
                seedtime_hour   = upd.seedtime_hour
            when not matched then
                insert (    id_user,     leeching,     seeding,     snatch_total,     snatch_unique,     bp_accrual_hour,     seedtime_hour)
                values (upd.id_user, upd.leeching, upd.seeding, upd.snatch_total, upd.snatch_unique, upd.bp_accrual_hour, upd.seedtime_hour)
            when not matched by source then
                delete
            END_SQL,
                UserStatus::enabled->value
        );
        $task?->info("user_summary_seed", $processed);

        $processed += $this->pg()->query(<<<END_SQL
            merge into user_summary_tgroup ust
            using (
                with artist(id_user, total) as (
                    select id_user, total
                    from relay.v_summary_torrents_artists
                ),
                download(id_user, dl_total, dl_unique) as (
                    select id_user, dl_total, dl_unique
                    from relay.v_summary_users_downloads
                ),
                upload(id_user, up_total, up_unique, up_perfect, up_perfecter) as (
                    select id_user, total, unique_group, perfect_flac, perfecter_flac
                    from relay.v_summary_tgroup
                )
                select um."ID",
                    coalesce(artist.total,        0),
                    coalesce(download.dl_total,   0),
                    coalesce(download.dl_unique,  0),
                    coalesce(upload.up_total,     0),
                    coalesce(upload.up_unique,    0),
                    coalesce(upload.up_perfect,   0),
                    coalesce(upload.up_perfecter, 0)
                from relay.users_main um
                left join artist   on (artist.id_user   = um."ID")
                left join download on (download.id_user = um."ID")
                left join upload   on (upload.id_user   = um."ID")
            ) as upd (id_user, artist_added, download_total, download_unique, upload, unique_group, perfect_flac, perfecter_flac)
            on ust.id_user = upd.id_user
            when matched and (
                    ust.artist_added    != upd.artist_added
                 or ust.download_total  != upd.download_total
                 or ust.download_unique != upd.download_unique
                 or ust.upload          != upd.upload
                 or ust.unique_group    != upd.unique_group
                 or ust.perfect_flac    != upd.perfect_flac
                 or ust.perfecter_flac  != upd.perfecter_flac
            ) then update set
                artist_added    = upd.artist_added,
                download_total  = upd.download_total,
                download_unique = upd.download_unique,
                upload          = upd.upload,
                unique_group    = upd.unique_group,
                perfect_flac    = upd.perfect_flac,
                perfecter_flac  = upd.perfecter_flac
            when not matched then
                insert (    id_user,     artist_added,     download_total,     download_unique,     upload,     unique_group,     perfect_flac,     perfecter_flac)
                values (upd.id_user, upd.artist_added, upd.download_total, upd.download_unique, upd.upload, upd.unique_group, upd.perfect_flac, upd.perfecter_flac)
            when not matched by source then
                delete
            END_SQL
        );
        $task?->info("user_summary_tgroup", $processed);

        return $processed;
    }

    public function refreshUseragentTracker(): int {
        // NB: useragents may be null if the initial announce from ocelot
        // was lost, e.g. because of a deadlock or queue overflow.
        return $this->pg()->query("
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
            $top = $this->pg()->columnex(<<<END_SQL
                select um."ID"
                from relay.users_main um
                inner join user_summary_tgroup ust on (ust.id_user = um."ID")
                where um."Enabled" = $1
                    and (um."Paranoia" is null or um."Paranoia" !~* 'uploaded')
                order by ust.upload desc
                limit $2
                END_SQL,
                    UserStatus::enabled->value, $limit
            );
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
        return $this->pg()->allex(<<<END_SQL
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
        return $this->pg()->columnex(<<<END_SQL
            select to_char(
                 now() - interval '11 month' + (interval '1 month' * generate_series(0, 11)), 'YYYY-MM'
            )
            union select 'total'
            order by 1
            END_SQL
        );
    }
}
