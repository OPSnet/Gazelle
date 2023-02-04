<?php

namespace Gazelle\User;

class Vote extends \Gazelle\BaseUser {
    final const Z_VAL    = 1.281728756502709;  // original
    final const Z_VAL_90 = 1.6448536251336989; // p-value .90
    final const Z_VAL_95 = 1.959963986120195;  //         .95

    final public const UPVOTE = 1;
    final public const DOWNVOTE = 2;

    protected const VOTE_USER_KEY = 'vote_user_%d';
    protected const VOTE_PAIR_KEY = 'vote_pair_%d';
    protected const VOTE_RECENT   = 'u_vote_%d';
    protected const VOTE_TOTAL    = 'u_vote_T_%d';
    protected const VOTED_USER    = 'user_voted_%d';
    protected const VOTED_GROUP   = 'group_voted_%d';

    protected array $tgroupInfo;
    protected array $topConfig = [];
    protected array $topJoin   = [];
    protected array $topWhere  = [];
    protected array $topArgs   = [];
    protected array $userVote;
    protected array $voteSummary;

    public function __construct(\Gazelle\User $user) {
        parent::__construct($user);
        $userKey = sprintf(self::VOTE_USER_KEY, $this->user->id());
        $userVote = self::$cache->get_value($userKey);
        if ($userVote === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    CASE WHEN Type = 'Up' THEN 1
                        WHEN Type = 'Down' THEN -1
                        ELSE 0
                     END as Vote
                FROM users_votes
                WHERE UserID = ?
                ", $this->user->id()
            );
            $userVote = self::$db->to_pair('GroupID', 'Vote', false);
            self::$cache->cache_value($userKey, $userVote, 0);
        }
        $this->userVote = $userVote;
    }

    public function flush(): Vote {
        self::$cache->delete_multi([
            sprintf(self::VOTE_RECENT, $this->user->id()),
            sprintf(self::VOTE_TOTAL, $this->user->id()),
        ]);
        return $this;
    }

    public function setTopLimit(int $limit) {
        $this->topConfig['limit'] = in_array($limit, [100, 250]) ? $limit : 25;
        return $this;
    }

    public function setTopTagList(array $tagList, bool $all) {
        $this->topConfig['tagList'] = $tagList;
        $this->topConfig['tagAll']  = $all;
        return $this;
    }

    public function setTopYearInterval(int $lower, int $higher) {
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
                $prevRank  = $rank;
            }
            $ranks[$id] = $prevRank;
            $prevScore = $score;
        }
        return $ranks;
    }

    /**
     * Gets where this album ranks overall.
     * @return int Rank
     */
    public function rankOverall(int $tgroupId) {
        $key = "voting_ranks_overall";
        $ranks = self::$cache->get_value($key);
        if ($ranks === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    Score
                FROM torrents_votes
                ORDER BY Score DESC
                LIMIT 100
            ");
            $ranks = $this->voteRanks(self::$db->to_pair(0, 1, false));
            self::$cache->cache_value($key, $ranks, 259200); // 3 days
        }
        return $ranks[$tgroupId] ?? false;
    }

    /**
     * Gets where this album ranks in its year.
     * @param int $year Year it was released
     * @return int Rank for its year
     */
    public function rankYear(int $tgroupId, int $year) {
        $key = "voting_ranks_year_$year";
        $ranks = self::$cache->get_value($year);
        if ($ranks == false) {
            self::$db->prepared_query("
                SELECT v.GroupID,
                    v.Score
                FROM torrents_votes AS v
                INNER JOIN torrents_group AS g ON (g.ID = v.GroupID)
                WHERE g.Year = ?
                ORDER BY v.Score DESC
                LIMIT 100
                ", $year
            );
            $ranks = $this->voteRanks(self::$db->to_pair(0, 1, false));
            self::$cache->cache_value($key, $ranks, 259200);
        }
        return $ranks[$tgroupId] ?? false;
    }

    /**
     * Gets where this album ranks in its decade.
     * @param int $year Year it was released
     * @return int Rank for its year
     */
    public function rankDecade(int $tgroupId, int $year) {
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
                LIMIT 100
                ", $year, $year
            );
            $ranks = $this->voteRanks(self::$db->to_pair(0, 1, false));
            self::$cache->cache_value($key, $ranks, 259200); // 3 days
        }

        return $ranks[$tgroupId] ?? false;
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

    public function links(int $tgroupId): string {
        return self::$twig->render('vote/links.twig', [
            'group_id' => $tgroupId,
            'score'    => $this->score($this->total($tgroupId), $this->totalUp($tgroupId)),
            'vote'     => $this->userVote[$tgroupId] ?? 0,
            'viewer'   => $this->user,
        ]);
    }

    public function total(int $tgroupId): int {
        return $this->tgroupInfo($tgroupId)['Total'];
    }

    public function totalUp(int $tgroupId): int {
        return $this->tgroupInfo($tgroupId)['Ups'];
    }

    public function totalDown(int $tgroupId): int {
        return $this->total($tgroupId) - $this->totalUp($tgroupId);
    }

    public function vote(int $tgroupId): int {
        return $this->userVote[$tgroupId] ?? 0;
    }

    /**
     * Returns an array with torrent group vote data
     * @return array (Ups, Total, Score)
     */
    public function tgroupInfo(int $tgroupId): array {
        if (!isset($this->tgroupInfo) || empty($this->tgroupInfo)) {
            $key = sprintf(self::VOTED_GROUP, $tgroupId);
            $tgroupInfo = self::$cache->get_value($key);
            if ($tgroupInfo === false || is_null($tgroupInfo)) {
                $tgroupInfo = self::$db->rowAssoc("
                    SELECT Ups, `Total`, Score FROM torrents_votes WHERE GroupID = ?
                    ", $tgroupId
                ) ?? ['Ups' => 0, 'Total' => 0, 'Score' => 0];
                self::$cache->cache_value($key, $tgroupInfo, 259200); // 3 days
            }
            $this->tgroupInfo = $tgroupInfo;
        }
        return $this->tgroupInfo;
    }

    /**
     * Returns an array with User Vote data: GroupID and vote type
     * @return array [groupId => 0|1]
     */
    public function userVotes(): array {
        $key = sprintf(self::VOTED_USER, $this->user->id());
        $votes = self::$cache->get_value($key);
        if ($votes === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    CASE WHEN Type = 'Up' THEN 1 ELSE 0 END AS vote
                FROM users_votes
                WHERE UserID = ?
                ", $this->user->id()
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
    public function score(int $total, int $ups): float {
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
    public function upvote(int $tgroupId): array {
        return $this->castVote($tgroupId, 1);
    }

    /**
     * The user downvotes a release
     *
     * @return array [bool success, string reason]
     */
    public function downvote(int $tgroupId): array {
        return $this->castVote($tgroupId, -1);
    }

    protected function summary(int $tgroupId): array {
        [$total, $ups] = self::$db->row("
            SELECT count(*) AS Total,
                coalesce(sum(if(v.Type = 'Up', 1, 0)), 0) AS Ups
            FROM users_votes AS v
            WHERE v.GroupID = ?
            ", $tgroupId
        );
        return [$total, $ups, $this->score($total, $ups)];
    }

    /**
     * Handle the mechanics of casting a vote
     *
     * @return array [bool success, string reason]
     */
    protected function castVote(int $tgroupId, int $direction): array {
        if (isset($this->userVote[$tgroupId])) {
            return [false, 'already-voted'];
        }
        $up = $direction === 1 ? 1 : 0;

        // update db
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT IGNORE INTO users_votes
                   (UserID, GroupID, upvote, Type)
            VALUES (?,      ?,       ?,      ?)
            ", $this->user->id(), $tgroupId, $up, $up ? 'Up' : 'Down'
        );
        [$total, $ups, $score] = $this->summary($tgroupId);
        self::$db->prepared_query("
            INSERT INTO torrents_votes
                   (GroupID, Ups, Score, Total)
            VALUES (?,       ?,   ?,     1)
            ON DUPLICATE KEY UPDATE
                Total = ?,
                Ups   = ?,
                Score = ?
            ", $tgroupId, $ups, $score, $total, $ups, $score
        );
        $this->tgroupInfo = self::$db->rowAssoc("
            SELECT Ups, `Total`, Score FROM torrents_votes WHERE GroupID = ?
            ", $tgroupId
        );
        self::$db->commit();

        // update cache
        $this->userVote[$tgroupId] = $direction;
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->user->id()), $this->userVote, 259200); // 3 days
        self::$cache->cache_value(sprintf(self::VOTED_GROUP, $tgroupId), $this->tgroupInfo, 259200);
        self::$cache->delete_value(sprintf(self::VOTE_PAIR_KEY, $tgroupId));
        $this->flush();

        return [true, 'voted'];
    }

    /**
     * Clear a vote on this release group
     */
    public function clear(int $tgroupId): array {
        if (!isset($this->userVote[$tgroupId])) {
            return [false, 'not-voted'];
        }

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM users_votes
            WHERE UserID = ? AND GroupID = ?
            ", $this->user->id(), $tgroupId
        );
        [$total, $ups, $score] = $this->summary($tgroupId);
        self::$db->prepared_query("
            UPDATE torrents_votes SET
                Total = ?,
                Ups   = ?,
                Score = ?
            WHERE GroupID = ?
            ", $total, $ups, $score, $tgroupId
        );
        $this->tgroupInfo = self::$db->rowAssoc("
            SELECT Ups, `Total`, Score FROM torrents_votes WHERE GroupID = ?
            ", $tgroupId
        );
        self::$db->commit();

        // Update cache
        unset($this->userVote[$tgroupId]);
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->user->id()), $this->userVote, 259200);
        self::$cache->cache_value(sprintf(self::VOTED_GROUP, $tgroupId), $this->tgroupInfo, 259200);
        self::$cache->delete_value(sprintf(self::VOTE_PAIR_KEY, $tgroupId));
        $this->flush();

        return [true, 'cleared'];
    }

    public function recent(\Gazelle\Manager\TGroup $tgMan): array {
        $key = sprintf(self::VOTE_RECENT, $this->user->id());
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
                ", $this->user->id()
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
            $key = sprintf(self::VOTE_TOTAL, $this->user->id());
            $voteSummary = self::$cache->get_value($key);
            if ($voteSummary === false) {
                $voteSummary = self::$db->rowAssoc("
                    SELECT count(*) AS total, coalesce(sum(Type='Up'), 0) AS up FROM users_votes WHERE UserID = ?
                    ", $this->user->id()
                );
                self::$cache->cache_value($key, $voteSummary, 0);
            }
            $this->voteSummary = $voteSummary;
        }
        return match($mask) {
            self::UPVOTE   => (int)$this->voteSummary['up'],
            self::DOWNVOTE => $this->voteSummary['total'] - $this->voteSummary['up'],
            default        => $this->voteSummary['total'],
        };
    }

    public function userPage(\Gazelle\Manager\TGroup $tgMan, int $mask, int $limit, int $offset): array {
        $cond = ['UserID = ?'];
        $args = [$this->user->id()];
        if ($mask === self::UPVOTE) {
            $cond[] = 'Type = ?';
            $args[] = 'Up';
        }
        elseif ($mask === self::DOWNVOTE) {
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
