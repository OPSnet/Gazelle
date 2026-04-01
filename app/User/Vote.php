<?php

namespace Gazelle\User;

class Vote extends \Gazelle\BaseUser {
    final public const tableName = 'users_votes';

    final public const UPVOTE   = 1;
    final public const DOWNVOTE = 2;

    final protected const VOTE_USER_KEY = 'tgv_u_%d';
    final protected const VOTE_PAIR_KEY = 'tgv_pair_%d';
    final protected const VOTE_RECENT   = 'tgv_uvote_2_%d';
    final protected const VOTE_TOTAL    = 'tgv_utotal_%d';
    final protected const VOTED_USER    = 'tgv_u_voted_%d';
    final protected const VOTED_TGROUP  = 'tgv_t_voted_%d';

    final public const VOTE_RANK_OVERALL = 'tgv_rank_overall';
    final public const VOTE_RANK_DECADE  = 'tgv_rank_year_%d';
    final public const VOTE_RANK_YEAR    = 'tgv_rank_decade_%d';

    protected int   $limit;
    protected array $config = [];
    protected array $join   = [];
    protected array $where  = [];
    protected array $args   = [];
    protected array $voteSummary;

    public function flush(): static {
        self::$cache->delete_multi([
            self::VOTE_RANK_OVERALL,
            sprintf(self::VOTE_RECENT, $this->user->id),
            sprintf(self::VOTE_TOTAL, $this->user->id),
            sprintf(self::VOTE_USER_KEY, $this->user->id),
            sprintf(self::VOTED_USER, $this->user->id),
        ]);
        unset($this->info);
        return $this;
    }

    protected function tgroupFlush(\Gazelle\TGroup $tgroup): static {
        self::$cache->delete_multi([
            sprintf(self::VOTED_TGROUP, $tgroup->id),
            sprintf(self::VOTE_PAIR_KEY, $tgroup->id),
        ]);
        return $this->flush();
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key  = sprintf(self::VOTE_USER_KEY, $this->user->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = $this->pg()->pair(
                'id_tgroup',
                'vote',
                <<<END_SQL
                select id_tgroup,
                    case when upvote then 1 else -1 end as vote
                from tgroup_vote
                where id_user = $1
                END_SQL,
                    $this->user->id
            );
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $info;
    }

    public function setTopLimit(int $limit): static {
        $this->config['limit'] = $limit;
        return $this;
    }

    public function setTopTagList(array $tagList, bool $all): static {
        $this->config['tagList'] = $tagList;
        $this->config['tagAll']  = $all;
        return $this;
    }

    public function setTopYearInterval(int $lower, int $higher): static {
        if ($lower > 0) {
            if ($higher > 0) {
                $this->join[] = 'inner join relay.torrents_group tg on (tg."ID" = tvs.id_tgroup)';
                $this->where[] = "tg.\"Year\" between {$this->pg()->placeholder(count($this->args))} and {$this->pg()->placeholder(1 + count($this->args))}";
                $this->args[] = min($lower, $higher);
                $this->args[] = max($lower, $higher);
            } else {
                $this->join[] = 'inner join relay.torrents_group tg on (tg."ID" = tvs.id_tgroup)';
                $this->where[] = "tg.\"Year\" >= 1 + {$this->pg()->placeholder(count($this->args))}";
                $this->args[] = $lower;
            }
        } elseif ($higher > 0) {
            $this->join[] = 'inner join relay.torrents_group tg on (tg."ID" = tvs.id_tgroup)';
            $this->where[] = "tg.\"Year\" <= {$this->pg()->placeholder(count($this->args))}";
            $this->args[] = $higher;
        }
         return $this;
    }

    public function ranking(\Gazelle\TGroup $tgroup, bool $viewAdvancedTop10): array {
        if ($tgroup->categoryName() != 'Music') {
            return [];
        }
        $ranking = [];
        $rank    = $this->rankOverall($tgroup);
        if ($rank) {
            $ranking['overall'] = [
                'rank'  => $rank,
                'title' => '<a href="top10.php?type=votes">overall</a>',
            ];
        }
        $year      = $tgroup->year();
        $decade    = $year - ($year % 10);
        $decadeEnd = $decade + 9;
        $rank      = $this->rankDecade($tgroup);
        if ($rank) {
            $ranking['decade'] = [
                'rank'  => $rank,
                'title' => $viewAdvancedTop10
                    ? "for the <a href=\"top10.php?advanced=1&amp;type=votes&amp;year1=$decade&amp;year2=$decadeEnd\">{$decade}s</a>"
                    : "for the {$decade}s",
            ];
        }
        $rank = $this->rankYear($tgroup);
        if ($rank) {
            $ranking['year'] = [
                'rank'  => $rank,
                'title' => $viewAdvancedTop10
                    ? "for <a href=\"top10.php?advanced=1&amp;type=votes&amp;year1=$year\">$year</a>"
                    : "for $year",
            ];
        }
        return $ranking;
    }

    /**
     * Gets where this album ranks overall.
     */
    public function rankOverall(\Gazelle\TGroup $tgroup): int|false {
        $key = self::VOTE_RANK_OVERALL;
        $ranks = self::$cache->get_value($key);
        if ($ranks === false) {
            $ranks = $this->pg()->pair(
                'id_tgroup',
                'tier',
                <<<END_SQL
                select tvs.id_tgroup,
                    rank() over (order by tvs.score desc) as tier
                from tgroup_vote_summary tvs
                limit 1000
                END_SQL
            );
            self::$cache->cache_value($key, $ranks, 259200); // 3 days
        }
        return $ranks[$tgroup->id] ?? false;
    }

    /**
     * Gets where this album ranks in its decade.
     */
    public function rankDecade(\Gazelle\TGroup $tgroup): int|false {
        $decade  = $tgroup->year();
        $decade -= $decade % 10; // First year of the decade
        $key     = sprintf(self::VOTE_RANK_DECADE, $decade);
        $ranks   = self::$cache->get_value($key);
        if ($ranks === false) {
            $ranks = $this->pg()->pair(
                'id_tgroup',
                'tier',
                <<<END_SQL
                select tvs.id_tgroup,
                    rank() over (order by tvs.score desc) as tier
                from tgroup_vote_summary tvs
                inner join relay.torrents_group tg on (tg."ID" = tvs.id_tgroup)
                where tg."CategoryID" = 1
                  and tg."Year" between $1 and $1 + 9
                limit 1000
                END_SQL,
                    $decade
            );
            self::$cache->cache_value($key, $ranks, 259200); // 3 days
        }
        return $ranks[$tgroup->id] ?? false;
    }

    /**
     * Gets where this album ranks in its year.
     */
    public function rankYear(\Gazelle\TGroup $tgroup): int|false {
        $year  = $tgroup->year();
        $key   = sprintf(self::VOTE_RANK_YEAR, $year);
        $ranks = self::$cache->get_value($key);
        if ($ranks === false) {
            $ranks = $this->pg()->pair(
                'id_tgroup',
                'tier',
                <<<END_SQL
                select tvs.id_tgroup,
                    rank() over (order by tvs.score desc) as tier
                from tgroup_vote_summary tvs
                inner join relay.torrents_group tg on (tg."ID" = tvs.id_tgroup)
                where tg."CategoryID" = 1
                  and tg."Year" = $1
                limit 1000
                END_SQL,
                    $year
            );
            self::$cache->cache_value($key, $ranks, 259200); // 3 days
        }
        return $ranks[$tgroup->id] ?? false;
    }

    public function topVotes(bool $bypassCache = false): array {
        $key = "top10_votes_{$this->config['limit']}"
            . ($this->where !== []
                ? signature(
                    implode('|', array_merge($this->where, $this->args, [(int)isset($this->config['tagAll'])])),
                    TOP10_SALT
                )
                : ''
            );
        $voteList = self::$cache->get_value($key);
        if ($voteList === false || $bypassCache) {
            if (isset($this->config['tagList'])) {
                if ($this->config['tagAll']) {
                    foreach ($this->config['tagList'] as $tag) {
                        $this->where[] = <<<END_SQL
                        exists (
                            select 1
                            from relay.torrents_tags tt
                            inner join relay.tags t on (t."ID" = tt."TagID")
                            where tt."GroupID" = tg."ID"
                                and t."Name" = {$this->pg()->placeholder(count($this->args))}
                        )
                        END_SQL;
                        $this->args[] = $tag;
                    }
                } else {
                    $placeholder = placeholders_pg($this->config['tagList'], '%s', 1, count($this->args));
                    $this->where[] = <<<END_SQL
                    exists (
                        select 1
                        from relay.torrents_tags tt
                        inner join relay.tags t ON (t."ID" = tt."TagID")
                        where tt."GroupID" = tg."ID"
                            and t."Name" in ($placeholder)
                    )
                    END_SQL;
                    $this->args = array_merge($this->args, $this->config['tagList']);
                }
                $this->join[] = 'inner join relay.torrents_group tg on (tg."ID" = tvs.id_tgroup)';
            }
            $join = implode(' ', $this->join);
            $this->where[] = 'score > 0';
            $where = implode(' and ', $this->where);
            $limit = "limit {$this->pg()->placeholder(count($this->args))}";
            $this->args[] = $this->config['limit'] ?? 25;
            $voteList = $this->pg()->allByKeyex(
                'id_tgroup',
                $sql = <<<END_SQL
                    select tvs.id_tgroup,
                        tvs.upvote,
                        tvs.total,
                        tvs.score,
                        rank() over (order by tvs.score desc) as tier
                    from tgroup_vote_summary tvs
                    $join
                    where $where
                    order by tvs.score desc
                    $limit
                END_SQL,
                ...$this->args
            );
            self::$cache->cache_value($key, $voteList, 3600);
        }
        return $voteList;
    }

    public function links(\Gazelle\TGroup $tgroup): string {
        return self::$twig->render('vote/links.twig', [
            'group_id' => $tgroup->id,
            'score'    => $this->tgroupInfo($tgroup)['score'],
            'vote'     => $this->info()[$tgroup->id] ?? 0,
            'viewer'   => $this->user,
        ]);
    }

    public function total(\Gazelle\TGroup $tgroup): int {
        return $this->tgroupInfo($tgroup)['total'];
    }

    public function totalUp(\Gazelle\TGroup $tgroup): int {
        return $this->tgroupInfo($tgroup)['upvote'];
    }

    public function totalDown(\Gazelle\TGroup $tgroup): int {
        return $this->total($tgroup) - $this->totalUp($tgroup);
    }

    public function vote(\Gazelle\TGroup $tgroup): int {
        return $this->info()[$tgroup->id] ?? 0;
    }

    /**
     * Returns an array with torrent group vote data
     * @return array (Ups, Total, Score)
     */
    public function tgroupInfo(\Gazelle\TGroup $tgroup): array {
        $key = sprintf(self::VOTED_TGROUP, $tgroup->id);
        $tgroupInfo = self::$cache->get_value($key);
        if ($tgroupInfo === false) {
            $tgroupInfo = $this->pg()->rowAssocex("
                select tvs.upvote,
                    tvs.total,
                    tvs.score
                from tgroup_vote_summary tvs
                where id_tgroup = $1
                ", $tgroup->id
            ) ?? ['upvote' => 0, 'total' => 0, 'score' => 0];
            self::$cache->cache_value($key, $tgroupInfo, 259200); // 3 days
        }
        return $tgroupInfo;
    }

    /**
     * Returns an array with User Vote data: GroupID and vote type
     * @return array [groupId => 0|1]
     */
    public function userVotes(): array {
        $key = sprintf(self::VOTED_USER, $this->user->id);
        $votes = self::$cache->get_value($key);
        if ($votes === false) {
            $votes = $this->pg()->pair(
                'id_tgroup',
                'vote',
                <<<END_SQL
                    select id_tgroup,
                        upvote
                    from tgroup_vote
                    where id_user = $1
                END_SQL,
                    $this->user->id
            );
            self::$cache->cache_value($key, $votes, 86400);
        }
        return $votes;
    }

    public function score(\Gazelle\TGroup $tgroup): float {
        return $this->summary($tgroup)[2];
    }

    protected function summary(\Gazelle\TGroup $tgroup): array {
        return $this->pg()->rowex('
            select total,
                upvote,
                score
            from tgroup_vote_summary
            where id_tgroup = $1
            ', $tgroup->id
        ) ?? [0, 0, 0.0];
    }

    /**
     * The user upvotes a release
     *
     * @return array [bool success, string reason]
     */
    public function upvote(\Gazelle\TGroup $tgroup): array {
        return $this->castVote($tgroup, true);
    }

    /**
     * The user downvotes a release
     *
     * @return array [bool success, string reason]
     */
    public function downvote(\Gazelle\TGroup $tgroup): array {
        return $this->castVote($tgroup, false);
    }

    /**
     * Handle the mechanics of casting a vote
     *
     * @return array [bool success, string reason]
     */
    protected function castVote(\Gazelle\TGroup $tgroup, bool $upvote): array {
        if (isset($this->info()[$tgroup->id])) {
            return [false, 'already-voted'];
        }
        $up = $upvote === true ? 1 : 0;

        // update db
        $this->pg()->begin();
        $this->pg()->query("
            insert into tgroup_vote
                   (id_tgroup, id_user, upvote)
            values ($1,        $2,      $3)
            on conflict (id_tgroup, id_user)
                do nothing
            ", $tgroup->id, $this->user->id, $upvote
        );
        $this->pg()->query("
            delete from tgroup_vote_summary where id_tgroup = $1
            ", $tgroup->id
        );
        $this->pg()->query("
            insert into tgroup_vote_summary (id_tgroup, total, upvote)
            select id_tgroup,
                count(*),
                sum(upvote::int)
            from tgroup_vote
            where id_tgroup = $1
            group by id_tgroup
            ", $tgroup->id
        );
        $this->pg()->commit();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT IGNORE INTO users_votes
                   (UserID, GroupID, upvote, Type)
            VALUES (?,      ?,       ?,      ?)
            ", $this->user->id, $tgroup->id, $up, $up ? 'Up' : 'Down'
        );
        [$total, $ups, $score] = $this->summary($tgroup);
        self::$db->prepared_query("
            INSERT INTO torrents_votes
                   (GroupID, Ups, Score, Total)
            VALUES (?,       ?,   ?,     1)
            ON DUPLICATE KEY UPDATE
                Total = ?,
                Ups   = ?,
                Score = ?
            ", $tgroup->id, $ups, $score, $total, $ups, $score
        );
        self::$db->commit();

        // update cache
        $this->info[$tgroup->id] = $upvote;
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->user->id), $this->info, 259200); // 3 days
        $this->tgroupFlush($tgroup);

        return [true, 'voted'];
    }

    /**
     * Clear a vote on this release group
     */
    public function clear(\Gazelle\TGroup $tgroup): array {
        if (!isset($this->info()[$tgroup->id])) {
            return [false, 'not-voted'];
        }

        $this->pg()->begin();
        $this->pg()->query('
            delete from tgroup_vote
            where id_tgroup = $1
                and id_user = $2
            ', $tgroup->id, $this->user->id
        );
        $this->pg()->query('
            delete from tgroup_vote_summary
            where id_tgroup = $1
            ', $tgroup->id
        );
        $this->pg()->query("
            insert into tgroup_vote_summary (id_tgroup, total, upvote)
            select id_tgroup,
                count(*),
                sum(upvote::int)
            from tgroup_vote
            where id_tgroup = $1
            group by id_tgroup
            ", $tgroup->id
        );
        $this->pg()->commit();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM users_votes
            WHERE UserID = ? AND GroupID = ?
            ", $this->user->id, $tgroup->id
        );
        [$total, $ups, $score] = $this->summary($tgroup);
        self::$db->prepared_query("
            UPDATE torrents_votes SET
                Total = ?,
                Ups   = ?,
                Score = ?
            WHERE GroupID = ?
            ", $total, $ups, $score, $tgroup->id
        );
        self::$db->commit();

        // Update cache
        unset($this->info[$tgroup->id]);
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->user->id), $this->info, 259200);
        $this->tgroupFlush($tgroup);

        return [true, 'cleared'];
    }

    public function recent(
        \Gazelle\Manager\TGroup $tgMan = new \Gazelle\Manager\TGroup()
    ): array {
        $key = sprintf(self::VOTE_RECENT, $this->user->id);
        $recent = self::$cache->get_value($key);
        if ($recent === false) {
            $recent = $this->pg()->allex(<<<END_SQL
                select tv.id_tgroup as group_id,
                    tv.upvote
                from tgroup_vote tv
                inner join relay.torrents_group tg ON (tg."ID" = tv.id_tgroup)
                where tg."WikiImage" != ''
                    and tv.id_user = $1
                order by tv.created desc
                limit 5
                END_SQL,
                    $this->user->id
            );
            self::$cache->cache_value($key, $recent, 0);
        }
        foreach ($recent as &$r) {
            $r['tgroup'] = $tgMan->findById($r['group_id']);
        }
        return $recent;
    }

    public function userTotal(int $mask): int {
        if (!isset($this->voteSummary)) {
            $key = sprintf(self::VOTE_TOTAL, $this->user->id);
            $voteSummary = self::$cache->get_value($key);
            if ($voteSummary === false) {
                $voteSummary = $this->pg()->rowAssocex(<<<END_SQL
                    select count(*) AS total,
                        sum(upvote::int) as up
                    from tgroup_vote
                    where id_user = $1
                    END_SQL,
                        $this->user->id
                ) ?? ['total' => 0, 'upvote' => 0];
                self::$cache->cache_value($key, $voteSummary, 0);
            }
            $this->voteSummary = $voteSummary;
        }
        return match ($mask) {
            self::UPVOTE   => (int)$this->voteSummary['up'],
            self::DOWNVOTE => $this->voteSummary['total'] - $this->voteSummary['up'],
            default        => $this->voteSummary['total'],
        };
    }

    public function userPage(\Gazelle\Manager\TGroup $tgMan, int $mask, int $limit, int $offset): array {
        $args   = [];
        $cond   = ["id_user = {$this->pg()->placeholder(count($args))}"];
        $args[] = $this->user->id;
        if ($mask === self::UPVOTE) {
            $cond[] = 'upvote is true';
        } elseif ($mask === self::DOWNVOTE) {
            $cond[] = 'upvote is false';
        }
        $limitClause = "limit {$this->pg()->placeholder(count($args))}";
        $args[] = $limit;
        $offsetClause = "offset {$this->pg()->placeholder(count($args))}";
        $args[] = $offset;
        $where  = "where " . implode(' and ', $cond);
        $page   = $this->pg()->allex(<<<END_SQL
            select id_tgroup as group_id,
                upvote::int as upvote
            from tgroup_vote
            $where
            order by created desc
            $limitClause $offsetClause
            END_SQL,
                ...$args
        );
        foreach ($page as &$p) {
            $p['tgroup'] = $tgMan->findById($p['group_id']);
        }
        return $page;
    }
}
