<?php

namespace Gazelle\User;

class Vote extends \Gazelle\BaseUser {
    const Z_VAL    = 1.281728756502709;  // original
    const Z_VAL_90 = 1.6448536251336989; // p-value .90
    const Z_VAL_95 = 1.959963986120195;  //         .95

    public const UPVOTE = 1;
    public const DOWNVOTE = 2;

    protected const VOTE_USER_KEY = 'vote_user_%d';
    protected const VOTE_PAIR_KEY = 'vote_pair_%d';
    protected const VOTE_SIMILAR  = 'vote_similar_albums_%d';
    protected const VOTE_RECENT   = 'u_vote_%d';
    protected const VOTE_TOTAL    = 'u_vote_T_%d';
    protected const VOTED_USER    = 'user_voted_%d';
    protected const VOTED_GROUP   = 'group_voted_%d';

    protected array $groupVote = [];
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

    public function flush() {
        self::$cache->deleteMulti([
            sprintf(self::VOTE_PAIR_KEY, $this->groupId),
            sprintf(self::VOTE_RECENT, $this->user->id()),
            sprintf(self::VOTE_TOTAL, $this->user->id()),
        ]);
    }

    public function setGroupId(int $groupId) {
        $this->groupVote = [];
        $this->groupId = $groupId;
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
    public function rankOverall() {
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
        return $ranks[$this->groupId] ?? false;
    }

    /**
     * Gets where this album ranks in its year.
     * @param int $year Year it was released
     * @return int Rank for its year
     */
    public function rankYear(int $year) {
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
        return $ranks[$this->groupId] ?? false;
    }

    /**
     * Gets where this album ranks in its decade.
     * @param int $year Year it was released
     * @return int Rank for its year
     */
    public function rankDecade(int $year) {
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

        return $ranks[$this->groupId] ?? false;
    }

    public function topVotes(): array {
        $key = 'top10_votes_' . $this->topConfig['limit']
            . ($this->topWhere
                ? md5(implode('|', array_merge($this->topWhere, $this->topArgs, [(int)isset($this->topConfig['tagAll']]))))
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
            foreach ($results as $groupID => $votes) {
                $topVotes[$groupID] = [
                    'Ups'      => $votes['Ups'],
                    'Total'    => $votes['Total'],
                    'Score'    => $votes['Score'],
                    'sequence' => $ranks[$groupID],
                ];
            }
            self::$cache->cache_value($key, $topVotes, 3600);
        }
        return $topVotes;
    }

    public function similarVote(): array {
        $key = sprintf(self::VOTE_SIMILAR, $this->groupId);
        $similar = self::$cache->get_value($key);
        if ($similar === false || isset($similar[$this->groupId])) {
            self::$db->prepared_query("
                SELECT v.GroupID
                FROM (
                    SELECT UserID
                    FROM users_votes
                    WHERE Type='Up' AND GroupID = ?
                ) AS a
                INNER JOIN users_votes AS v USING (UserID)
                WHERE v.GroupID != ?
                GROUP BY v.GroupID
                HAVING sum(if(v.Type='Up', 1, 0)) > 0
                    AND binomial_ci(sum(if(v.Type = 'Up', 1, 0)), count(*)) > 0.3
                ORDER BY binomial_ci(sum(if(v.Type = 'Up', 1, 0)), count(*)),
                    count(*) DESC
                LIMIT 10
                ", $this->groupId, $this->groupId
            );
            $similar = self::$db->collect(0);
            self::$cache->cache_value($key, $similar, 3600);
        }
        return $similar;
    }

    public function links(): string {
        return self::$twig->render('vote/links.twig', [
            'group_id' => $this->groupId,
            'score'    => $this->score($this->total(), $this->totalUp()),
            'vote'     => $this->userVote[$this->groupId] ?? 0,
            'viewer'   => $this->user,
        ]);
    }

    public function total(): int {
        return $this->tgroupInfo()['Total'];
    }

    public function totalUp(): int {
        return $this->tgroupInfo()['Ups'];
    }

    public function totalDown(): int {
        $group = $this->tgroupInfo();
        return $group['Total'] - $group['Ups'];
    }

    public function vote(): int {
        return $this->userVote[$this->groupId] ?? 0;
    }

    /**
     * Returns an array with torrent group vote data
     * @return array (Upvotes, Total Votes)
     */
    public function tgroupInfo(): array {
        if (empty($this->groupVote)) {
            $key = sprintf(self::VOTED_GROUP, $this->groupId);
            $groupVote = self::$cache->get_value($key);
            if ($groupVote === false) {
                $groupVote = self::$db->rowAssoc("
                    SELECT Ups, `Total`, Score FROM torrents_votes WHERE GroupID = ?
                    ", $this->groupId
                );
                if (is_null($groupVote)) {
                    $groupVote = ['Ups' => 0, 'Total' => 0, 'Score' => 0];
                }
                self::$cache->cache_value($key, $groupVote, 259200); // 3 days
            }
            $this->groupVote = $groupVote;
        }
        return $this->groupVote;
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
            self::$cache->cache_value($key, $votes);
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
    public function upvote(): array {
        return $this->castVote(1);
    }

    /**
     * The user downvotes a release
     *
     * @return array [bool success, string reason]
     */
    public function downvote(): array {
        return $this->castVote(-1);
    }

    protected function summary(): array {
        [$total, $ups] = self::$db->row("
            SELECT count(*) AS Total,
                coalesce(sum(if(v.Type = 'Up', 1, 0)), 0) AS Ups
            FROM users_votes AS v
            WHERE v.GroupID = ?
            ", $this->groupId
        );
        return [$total, $ups, $this->score($total, $ups)];
    }

    /**
     * Handle the mechanics of casting a vote
     *
     * @return array [bool success, string reason]
     */
    protected function castVote(int $direction): array {
        if (isset($this->userVote[$this->groupId])) {
            return [false, 'already-voted'];
        }
        $up = $direction === 1 ? 1 : 0;

        // update db
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT IGNORE INTO users_votes
                   (UserID, GroupID, upvote, Type)
            VALUES (?,      ?,       ?,      ?)
            ", $this->user->id(), $this->groupId, $up, $up ? 'Up' : 'Down'
        );
        [$total, $ups, $score] = $this->summary();
        self::$db->prepared_query("
            INSERT INTO torrents_votes
                   (GroupID, Ups, Score, Total)
            VALUES (?,       ?,   ?,     1)
            ON DUPLICATE KEY UPDATE
                Total = ?,
                Ups   = ?,
                Score = ?
            ", $this->groupId, $ups, $score, $total, $ups, $score
        );
        self::$db->commit();

        // update cache
        $this->userVote[$this->groupId] = $direction;
        $this->groupVote['Total'] = $total;
        $this->groupVote['Ups']   = $ups;
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->user->id()), $this->userVote, 259200); // 3 days
        self::$cache->cache_value(sprintf(self::VOTED_GROUP, $this->groupId), $this->groupVote, 259200);
        $this->flush();

        return [true, 'voted'];
    }

    /**
     * Clear a vote on this release group
     */
    public function clear(): array {
        if (!isset($this->userVote[$this->groupId])) {
            return [false, 'not-voted'];
        }
        $up = $this->userVote[$this->groupId] === 1 ? 1 : 0;

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM users_votes
            WHERE UserID = ? AND GroupID = ?
            ", $this->user->id(), $this->groupId
        );
        [$total, $ups, $score] = $this->summary();
        self::$db->prepared_query("
            UPDATE torrents_votes SET
                Total = ?,
                Ups   = ?,
                Score = ?
            WHERE GroupID = ?
            ", $total, $ups, $score, $this->groupId
        );
        self::$db->commit();

        // Update cache
        unset($this->userVote[$this->groupId]);
        $this->groupVote['Total'] = $total;
        $this->groupVote['Ups']   = $ups;
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->user->id()), $this->userVote, 259200);
        self::$cache->cache_value(sprintf(self::VOTED_GROUP, $this->groupId), $this->groupVote, 259200);
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
        switch ($mask) {
            case self::UPVOTE:
                return (int)$this->voteSummary['up'];
            case self::DOWNVOTE:
                return $this->voteSummary['total'] - $this->voteSummary['up'];
            default:
                return $this->voteSummary['total'];
        }
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
