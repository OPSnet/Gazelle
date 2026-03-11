<?php

namespace Gazelle\Contest;

use Gazelle\Enum\UserStatus;
use Gazelle\Enum\SourceDB;

/* Contest based on how many requests filled
 *
 * Note: the queries all perform a join on the torrents table
 * which looks useless, but offers an additional guarantee
 * that the request is valid.
 */

class RequestFill extends AbstractContest {
    public function leaderboard(
        int $limit,
        int $offset,
        \Gazelle\Manager\Torrent $torMan = new \Gazelle\Manager\Torrent(),
    ): array {
        $key = sprintf(\Gazelle\Contest::CONTEST_LEADERBOARD_CACHE_KEY,
            $this->id, (int)($offset / CONTEST_ENTRIES_PER_PAGE)
        );
        $leaderboard = self::$cache->get_value($key);
        if ($leaderboard === false) {
            $leaderboard = $this->pg()->allex(<<<END_SQL
                select l.user_id,
                    l.entry_count,
                    l.last_entry_id,
                    t.created as last_upload,
                    t."GroupID" as group_id
                from relay.contest_leaderboard   l
                inner join relay.torrents        t   on (t."ID" = l.last_entry_id)
                inner join request               r   on (r.id_filler = t."UserID" and t."ID" = r.id_torrent)
                inner join relay.users_main      um  on (um."ID" = l.user_id)
                inner join relay.xbt_files_users xfu on (xfu.fid = t."ID" and xfu.uid = t."UserID")
                where xfu.remaining   = 0
                    and r.id_filler  != r.id_user
                    and um."Enabled"  = $1
                    and l.contest_id  = $2
                order by l.entry_count desc, t.created asc, l.user_id asc
                limit $3 offset $4
                END_SQL,
                    UserStatus::enabled->value,
                    $this->id,
                    $limit,
                    $offset,
            );

            for ($i = 0, $leaderboardCount = count($leaderboard); $i < $leaderboardCount; $i++) {
                $torrent = $torMan->findById($leaderboard[$i]['last_entry_id']);
                $leaderboard[$i]['last_entry_link']
                    = $torrent->groupLink() . ' ' . $torrent->label();
            }
            self::$cache->cache_value($key, $leaderboard, 3600);
        }
        return $leaderboard;
    }

    public function rankerSource(): SourceDB {
        return SourceDB::postgres;
    }

    public function ranker(): array {
        // note: this subquery is incorporated in a larger query
        // that will already contain a $1 placeholder prior to it,
        // hence the offset here must start from $2.
        return [<<<END_SQL
            select r.id_filler    as user_id,
                count(*)          as nr,
                max(r.id_torrent) as last_torrent
            from request                r
            inner join relay.torrents   t  on (t."ID" = r.id_torrent)
            inner join relay.users_main um on (um."ID" = r.id_filler)
            where r.id_filler != r.id_user
                and um."Enabled" = $2
                and r.created < $3
                and r.filled between $3 and $4
            group by r.id_filler
            END_SQL,
            [
                UserStatus::enabled->value,
                $this->begin, $this->end,
            ]
        ];
    }

    public function participationStats(): array {
        return $this->pg()->rowAssocex(<<<END_SQL
            select count(distinct r.id_request) as total_entries,
                count(distinct um."ID")         as total_users
            from relay.contest        c,
                relay.users_main      um
            inner join request        r on (r.id_filler = um."ID")
            inner join relay.torrents t on (t."ID" = r.id_torrent)
            where r.id_filler != r.id_user
                and r.created < c.date_begin
                and r.filled between c.date_begin and c.date_end
                and um."Enabled" = $1
                and c.contest_id = $2
            END_SQL,
                UserStatus::enabled->value,
                $this->id,
        );
    }

    public function userPayout(): array {
        return $this->pg()->allex(<<<END_SQL
            with c as (
                select um."ID"          as user_id,
                    count(r.id_request) as total_entries
                from relay.contest c,
                    relay.users_main      um
                inner join request        r on (r.id_filler = um."ID")
                inner join relay.torrents t on (t."ID" = r.id_torrent)
                where r.id_filler != r.id_user
                    and r.created < c.date_begin
                    and r.filled between c.date_begin and c.date_end
                    and um."Enabled" = $1
                    and c.contest_id = $2
                group by um."ID"
            )
            select um."ID"                   as user_id,
                coalesce(c.total_entries, 0) as total_entries
            from relay.users_main um
            left join c on (c.user_id = um."ID")
            where um."Enabled" = $1
                and um.created <= (
                    select date_end from relay.contest where contest_id = $2
                )
            END_SQL,
                UserStatus::enabled->value,
                $this->id,
        );
    }

    public function requestPairs(): array {
        $key = "contest_pairs_" . $this->id;
        $pairs = self::$cache->get_value($key);
        if ($pairs === false) {
            $pairs = $this->pg()->allex(<<<END_SQL
                select r.id_filler,
                    r.id_user,
                    count(*) as nr
                from request r
                inner join relay.torrents t on (t."ID" = r.id_torrent)
                where r.filled between $1 and $2
                group by r.id_filler, r.id_user
                having count(*) > 1
                order by count(*) desc, r.id_filler asc
                limit 100
                END_SQL,
                    $this->begin,
                    $this->end,
            );
            self::$cache->cache_value('contest_pairs_' . $this->id, $pairs, 3600);
        }
        return $pairs;
    }
}
