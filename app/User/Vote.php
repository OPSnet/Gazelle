<?php

namespace Gazelle\User;

class Vote extends \Gazelle\BaseUser {
    final public const tableName = 'users_votes';
    final protected const Z_VAL     = 1.281728756502709;  // original
    final protected const Z_VAL_90  = 1.6448536251336989; // p-value .90
    final protected const Z_VAL_95  = 1.959963986120195;  //         .95

    final public const UPVOTE   = 1;
    final public const DOWNVOTE = 2;

    protected const VOTE_USER_KEY = 'vote_user_%d';
    protected const VOTE_PAIR_KEY = 'vote_pair_%d';
    protected const VOTE_RECENT   = 'u_vote_%d';
    protected const VOTE_TOTAL    = 'u_vote_T_%d';
    protected const VOTED_USER    = 'user_voted_%d';
    protected const VOTED_GROUP   = 'group_voted_%d';

    protected int   $limit;
    protected array $topConfig = [];
    protected array $topJoin   = [];
    protected array $topWhere  = [];
    protected array $topArgs   = [];
    protected array $voteSummary;

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(self::VOTE_RECENT, $this->id()),
            sprintf(self::VOTE_TOTAL, $this->id()),
            sprintf(self::VOTE_USER_KEY, $this->id()),
            sprintf(self::VOTED_USER, $this->id()),
        ]);
        unset($this->info);
        return $this;
    }

    protected function tgroupFlush(\Gazelle\TGroup $tgroup): static {
        self::$cache->delete_multi([
            sprintf(self::VOTED_GROUP, $tgroup->id()),
            sprintf(self::VOTE_PAIR_KEY, $tgroup->id()),
        ]);
        return $this->flush();
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::VOTE_USER_KEY, $this->id());
        $info = self::$cache->get_value($key);
        if ($info === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    CASE WHEN Type = 'Up' THEN 1
                        WHEN Type = 'Down' THEN -1
                        ELSE 0
                     END as Vote
                FROM users_votes
                WHERE UserID = ?
                ", $this->id()
            );
            $info = self::$db->to_pair('GroupID', 'Vote', false);
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $info;
    }

    public function setTopLimit(int $limit): static {
        $this->topConfig['limit'] = $limit;
        return $this;
    }

    public function setTopTagList(array $tagList, bool $all): static {
        $this->topConfig['tagList'] = $tagList;
        $this->topConfig['tagAll']  = $all;
        return $this;
    }

    public function setTopYearInterval(int $lower, int $higher): static {
        if ($lower > 0) {
            if ($higher > 0) {
                $this->topJoin['torrents_group'] = 'INNER JOIN torrents_group tg ON (tg.ID = v.GroupID)';
                $this->topWhere[] = 'tg.Year BETWEEN ? AND ?';
                $this->topArgs[] = min($lower, $higher);
                $this->topArgs[] = max($lower, $higher);
            } else {
                $this->topJoin['torrents_group'] = 'INNER JOIN torrents_group tg ON (tg.ID = v.GroupID)';
                $this->topWhere[] = 'tg.Year >= ?';
                $this->topArgs[] = $lower;
            }
        } elseif ($higher > 0) {
            $this->topJoin['torrents_group'] = 'INNER JOIN torrents_group tg ON (tg.ID = v.GroupID)';
            $this->topWhere[] = 'tg.Year <= ?';
            $this->topArgs[] = $higher;
        }
         return $this;
    }

    /**
     * Calculate a leaderboard with ties, based on scores.
     * E.g. [10, 13, 17, 17, 20, 34, 34, 34, 50] returns [1, 2, 3, 3, 5, 6, 6, 6, 9]
     */
    public function voteRanks(array $list): array {
        $ranks = [];
        $rank = 0;
        $prevRank = 1;
        $prevScore = false;
        foreach ($list as $id => $score) {
            ++$rank;
            if ($prevScore && $prevScore !== $score) {
                $prevRank = $rank;
            }
            $ranks[$id] = $prevRank;
            $prevScore  = $score;
        }
        return $ranks;
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
        $key = "voting_ranks_overall";
        $ranks = self::$cache->get_value($key);
        if ($ranks === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    Score
                FROM torrents_votes
                ORDER BY Score DESC
                LIMIT 1000
            ");
            $ranks = $this->voteRanks(self::$db->to_pair('GroupID', 'Score', false));
            self::$cache->cache_value($key, $ranks, 259200); // 3 days
        }
        return $ranks[$tgroup->id()] ?? false;
    }

    /**
     * Gets where this album ranks in its year.
     */
    public function rankYear(\Gazelle\TGroup $tgroup): int|false {
        $year = $tgroup->year();
        $key = "voting_ranks_year_$year";
        $ranks = self::$cache->get_value($key);
        if ($ranks == false) {
            self::$db->prepared_query("
                SELECT v.GroupID,
                    v.Score
                FROM torrents_votes AS v
                INNER JOIN torrents_group AS g ON (g.ID = v.GroupID)
                WHERE g.Year = ?
                ORDER BY v.Score DESC
                LIMIT 1000
                ", $year
            );
            $ranks = $this->voteRanks(self::$db->to_pair('GroupID', 'Score', false));
            self::$cache->cache_value($key, $ranks, 259200);
        }
        return $ranks[$tgroup->id()] ?? false;
    }

    /**
     * Gets where this album ranks in its decade.
     */
    public function rankDecade(\Gazelle\TGroup $tgroup): int|false {
        $year = $tgroup->year();
        $year -= $year % 10; // First year of the decade
        $key = "voting_ranks_decade_$year";
        $ranks = self::$cache->get_value($key);
        if ($ranks === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    Score
                FROM torrents_votes  AS v
                INNER JOIN torrents_group AS g ON (g.ID = v.GroupID)
                WHERE g.Year BETWEEN ? AND ? + 9
                      AND g.CategoryID = 1
                ORDER BY Score DESC
                LIMIT 1000
                ", $year, $year
            );
            $ranks = $this->voteRanks(self::$db->to_pair('GroupID', 'Score', false));
            self::$cache->cache_value($key, $ranks, 259200); // 3 days
        }
        return $ranks[$tgroup->id()] ?? false;
    }

    public function topVotes(): array {
        $key = 'top10_votes_' . $this->topConfig['limit']
            . ($this->topWhere
                ? md5(implode('|', array_merge($this->topWhere, $this->topArgs, [(int)isset($this->topConfig['tagAll'])])))
            : '');
        $topVotes = self::$cache->get_value($key);
        if ($topVotes === false) {
            if (isset($this->topConfig['tagList'])) {
                if ($this->topConfig['tagAll']) {
                    foreach ($this->topConfig['tagList'] as $tag) {
                        $this->topWhere[] = "EXISTS (
                            SELECT 1
                            FROM torrents_tags tt
                            INNER JOIN tags t ON (t.ID = tt.TagID)
                            WHERE tt.GroupID = tg.ID
                                AND t.Name = ?
                        )";
                    }
                } else {
                    $this->topWhere[] = "EXISTS (
                        SELECT 1
                        FROM torrents_tags tt
                        INNER JOIN tags t ON (t.ID = tt.TagID)
                        WHERE tt.GroupID = tg.ID
                            AND t.Name IN (" . placeholders($this->topConfig['tagList']) . ")
                    )";
                }
                $this->topArgs = array_merge($this->topArgs, $this->topConfig['tagList']);
                $this->topJoin['torrents_group'] = 'INNER JOIN torrents_group tg ON (tg.ID = v.GroupID)';
            }
            $this->topWhere[] = 'Score > 0';
            $this->topArgs[] = $this->topConfig['limit'] ?? 25;

            self::$db->prepared_query("
                SELECT v.GroupID, v.Ups, v.Total, v.Score
                FROM torrents_votes AS v
                " . implode("\n", array_values($this->topJoin)) . "
                WHERE
                " . implode(' AND ', $this->topWhere) . "
                ORDER BY Score DESC
                LIMIT ?
                ", ...$this->topArgs
            );
            $results = self::$db->to_array('GroupID', MYSQLI_ASSOC, false);
            $ranks = $this->voteRanks(self::$db->to_pair('GroupID', 'Score', false));

            $topVotes = [];
            foreach ($results as $tgroupId => $votes) {
                $topVotes[$tgroupId] = [
                    'Ups'      => $votes['Ups'],
                    'Total'    => $votes['Total'],
                    'Score'    => $votes['Score'],
                    'sequence' => $ranks[$tgroupId],
                ];
            }
            self::$cache->cache_value($key, $topVotes, 3600);
        }
        return $topVotes;
    }

    public function links(\Gazelle\TGroup $tgroup): string {
        return self::$twig->render('vote/links.twig', [
            'group_id' => $tgroup->id(),
            'score'    => $this->score($tgroup),
            'vote'     => $this->info()[$tgroup->id()] ?? 0,
            'viewer'   => $this->user,
        ]);
    }

    public function total(\Gazelle\TGroup $tgroup): int {
        return $this->tgroupInfo($tgroup)['Total'];
    }

    public function totalUp(\Gazelle\TGroup $tgroup): int {
        return $this->tgroupInfo($tgroup)['Ups'];
    }

    public function totalDown(\Gazelle\TGroup $tgroup): int {
        return $this->total($tgroup) - $this->totalUp($tgroup);
    }

    public function vote(\Gazelle\TGroup $tgroup): int {
        return $this->info()[$tgroup->id()] ?? 0;
    }

    /**
     * Returns an array with torrent group vote data
     * @return array (Ups, Total, Score)
     */
    public function tgroupInfo(\Gazelle\TGroup $tgroup): array {
        $key = sprintf(self::VOTED_GROUP, $tgroup->id());
        $tgroupInfo = self::$cache->get_value($key);
        if ($tgroupInfo === false) {
            $tgroupInfo = self::$db->rowAssoc("
                SELECT Ups, `Total`, Score FROM torrents_votes WHERE GroupID = ?
                ", $tgroup->id()
            ) ?? ['Ups' => 0, 'Total' => 0, 'Score' => 0];
            self::$cache->cache_value($key, $tgroupInfo, 259200); // 3 days
        }
        return $tgroupInfo;
    }

    /**
     * Returns an array with User Vote data: GroupID and vote type
     * @return array [groupId => 0|1]
     */
    public function userVotes(): array {
        $key = sprintf(self::VOTED_USER, $this->id());
        $votes = self::$cache->get_value($key);
        if ($votes === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    CASE WHEN Type = 'Up' THEN 1 ELSE 0 END AS vote
                FROM users_votes
                WHERE UserID = ?
                ", $this->id()
            );
            $votes = self::$db->to_pair('GroupID', 'vote', false);
            self::$cache->cache_value($key, $votes, 86400);
        }
        return $votes;
    }

    /**
     * Calculate the binomial score given the total and upvotes.
     * Implementation of the algorithm described at
     *    http://www.evanmiller.org/how-not-to-sort-by-average-rating.html
     *
     * @return float score [0 <= x <= 1]
     */
    public function score(\Gazelle\TGroup $tgroup): float {
        return $this->calcScore($this->total($tgroup), $this->totalUp($tgroup));
    }

    public function calcScore(int $total, int $ups): float {
        if ($total <= 0 || $ups <= 0) {
            return 0.0;
        }
        $phat = $ups / $total;
        $numerator = $phat + self::Z_VAL * self::Z_VAL / (2 * $total)
            - self::Z_VAL * sqrt(($phat * (1 - $phat) + self::Z_VAL * self::Z_VAL / (4 * $total)) / $total);
        $denominator = 1 + self::Z_VAL * self::Z_VAL / $total;
        return $numerator / $denominator;
    }

    /**
     * The user upvotes a release
     *
     * @return array [bool success, string reason]
     */
    public function upvote(\Gazelle\TGroup $tgroup): array {
        return $this->castVote($tgroup, 1);
    }

    /**
     * The user downvotes a release
     *
     * @return array [bool success, string reason]
     */
    public function downvote(\Gazelle\TGroup $tgroup): array {
        return $this->castVote($tgroup, -1);
    }

    protected function summary(\Gazelle\TGroup $tgroup): array {
        [$total, $ups] = self::$db->row("
            SELECT count(*) AS Total,
                coalesce(sum(if(v.Type = 'Up', 1, 0)), 0) AS Ups
            FROM users_votes AS v
            WHERE v.GroupID = ?
            ", $tgroup->id()
        );
        return [$total, (int)$ups, $this->calcScore($total, (int)$ups)] ;
    }

    /**
     * Handle the mechanics of casting a vote
     *
     * @return array [bool success, string reason]
     */
    protected function castVote(\Gazelle\TGroup $tgroup, int $direction): array {
        if (isset($this->info()[$tgroup->id()])) {
            return [false, 'already-voted'];
        }
        $up = $direction === 1 ? 1 : 0;

        // update db
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT IGNORE INTO users_votes
                   (UserID, GroupID, upvote, Type)
            VALUES (?,      ?,       ?,      ?)
            ", $this->id(), $tgroup->id(), $up, $up ? 'Up' : 'Down'
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
            ", $tgroup->id(), $ups, $score, $total, $ups, $score
        );
        self::$db->commit();

        // update cache
        $this->info[$tgroup->id()] = $direction;
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->id()), $this->info, 259200); // 3 days
        $this->tgroupFlush($tgroup);

        return [true, 'voted'];
    }

    /**
     * Clear a vote on this release group
     */
    public function clear(\Gazelle\TGroup $tgroup): array {
        if (!isset($this->info()[$tgroup->id()])) {
            return [false, 'not-voted'];
        }

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM users_votes
            WHERE UserID = ? AND GroupID = ?
            ", $this->id(), $tgroup->id()
        );
        [$total, $ups, $score] = $this->summary($tgroup);
        self::$db->prepared_query("
            UPDATE torrents_votes SET
                Total = ?,
                Ups   = ?,
                Score = ?
            WHERE GroupID = ?
            ", $total, $ups, $score, $tgroup->id()
        );
        self::$db->commit();

        // Update cache
        unset($this->info[$tgroup->id()]);
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->id()), $this->info, 259200);
        $this->tgroupFlush($tgroup);

        return [true, 'cleared'];
    }

    public function recent(\Gazelle\Manager\TGroup $tgMan): array {
        $key = sprintf(self::VOTE_RECENT, $this->id());
        $recent = self::$cache->get_value($key);
        if ($recent === false) {
            self::$db->prepared_query("
                SELECT tg.ID AS group_id,
                    if(uv.Type = 'Up', 1, 0) AS upvote
                FROM users_votes uv
                INNER JOIN torrents_group tg ON (tg.ID = uv.GroupID)
                WHERE tg.WikiImage != ''
                    AND uv.UserID = ?
                ORDER BY uv.Time DESC
                LIMIT 5
                ", $this->id()
            );
            $recent = self::$db->to_array();
            self::$cache->cache_value($key, $recent, 0);
        }
        foreach ($recent as &$r) {
            $r['tgroup'] = $tgMan->findById($r['group_id']);
        }
        return $recent;
    }

    public function userTotal(int $mask): int {
        if (!isset($this->voteSummary)) {
            $key = sprintf(self::VOTE_TOTAL, $this->id());
            $voteSummary = self::$cache->get_value($key);
            if ($voteSummary === false) {
                $voteSummary = self::$db->rowAssoc("
                    SELECT count(*) AS total, coalesce(sum(Type='Up'), 0) AS up FROM users_votes WHERE UserID = ?
                    ", $this->id()
                );
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
        $cond = ['UserID = ?'];
        $args = [$this->id()];
        if ($mask === self::UPVOTE) {
            $cond[] = 'Type = ?';
            $args[] = 'Up';
        } elseif ($mask === self::DOWNVOTE) {
            $cond[] = 'Type = ?';
            $args[] = 'Down';
        }
        array_push($args, $limit, $offset);
        self::$db->prepared_query("
            SELECT GroupID AS group_id,
                if(Type = 'Up', 1, 0) AS upvote
            FROM users_votes
            WHERE " . implode(' AND ', $cond) . "
            ORDER BY Time DESC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        $page =  self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($page as &$p) {
            $p['tgroup'] = $tgMan->findById($p['group_id']);
        }
        return $page;
    }
}
