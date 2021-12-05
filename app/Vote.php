<?php

namespace Gazelle;

class Vote extends Base {
    const Z_VAL    = 1.281728756502709;  // original
    const Z_VAL_90 = 1.6448536251336989; // p-value .90
    const Z_VAL_95 = 1.959963986120195;  //         .95

    protected const VOTE_USER_KEY = 'vote_user_%d';
    protected const VOTE_PAIR_KEY = 'vote_pair_%d';
    protected const VOTE_SIMILAR  = 'vote_similar_albums_%d';
    protected const VOTED_USER    = 'user_voted_%d';
    protected const VOTED_GROUP   = 'group_voted_%d';

    protected $groupId;
    protected $groupVote;
    protected $userId;
    protected $userVote;

    protected $topConfig = [];
    protected $topJoin   = [];
    protected $topWhere  = [];
    protected $topArgs   = [];

    public function __construct(int $userId) {
        $this->userId = $userId;
        $userKey = sprintf(self::VOTE_USER_KEY, $userId);
        if (($this->userVote = self::$cache->get_value($userKey)) === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    CASE WHEN Type = 'Up' THEN 1
                        WHEN Type = 'Down' THEN -1
                        ELSE 0
                     END as Vote
                FROM users_votes
                WHERE UserID = ?
                ", $userId
            );
            $this->userVote = self::$db->to_pair('GroupID', 'Vote', false);
            self::$cache->cache_value($userKey, $this->userVote);
        }
    }

    public function setGroupId(int $groupId) {
        $this->groupVote = null;
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

    public function voteRanks(array $list) {
        $ranks = [];
        $rank = 0;
        $prevRank = false;
        $prevScore = false;
        foreach ($list as $id => $score) {
            ++$rank;
            if ($prevScore && $prevScore !== $score) {
                $prevRank  = $rank;
                $prevScore = $score;
            }
            $ranks[$id] = $prevRank;
        }
        return $ranks;
    }

    /**
     * Gets where this album ranks overall.
     * @return int Rank
     */
    public function rankOverall() {
        $key = "voting_ranks_overall";
        if (($ranks = self::$cache->get_value($key)) === false) {
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
        if (($ranks = self::$cache->get_value($year)) === false) {
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
        if (($ranks = self::$cache->get_value($key)) === false) {
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

    public function topVotes() {
        $key = 'top10_votes_' . $this->topConfig['limit']
            . ($this->topWhere
                ? md5(implode('|', array_merge($this->topWhere, $this->topArgs, [(int)$this->topConfig['tagAll']])))
            : '');
        if (($topVotes = self::$cache->get_value($key)) === false) {
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
            $groups = \Torrents::get_groups(array_keys($results));

            $topVotes = [];
            foreach ($results as $groupID => $votes) {
                $topVotes[$groupID] = array_merge(
                    $groups[$groupID], [
                        'Ups'   => $votes['Ups'],
                        'Total' => $votes['Total'],
                        'Score' => $votes['Score'],
                        'Rank'  => $ranks[$groupID],
                    ]
                );
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

    public function links(string $auth): string {
        return self::$twig->render('vote/links.twig', [
            'auth'     => $auth,
            'group_id' => $this->groupId,
            'score'    => $this->score($this->total(), $this->totalUp()),
            'vote'     => $this->userVote[$this->groupId] ?? 0,
        ]);
    }

    public function total(): int {
        return $this->loadGroup()['Total'];
    }

    public function totalUp(): int {
        return $this->loadGroup()['Ups'];
    }

    public function totalDown(): int {
        $group = $this->loadGroup();
        return $group['Total'] - $group['Ups'];
    }

    public function vote(): int {
        return $this->userVote[$this->groupId] ?? 0;
    }

    /**
     * Returns an array with torrent group vote data
     * @return array (Upvotes, Total Votes)
     */
    public function loadGroup(): array {
        if (!$this->groupVote) {
            $key = sprintf(self::VOTED_GROUP, $this->groupId);
            if (($this->groupVote = self::$cache->get_value($key)) === false) {
                $this->groupVote = self::$db->rowAssoc("
                    SELECT Ups, `Total`, Score FROM torrents_votes WHERE GroupID = ?
                    ", $this->groupId
                );
                if (is_null($this->groupVote)) {
                    $this->groupVote = ['Ups' => 0, 'Total' => 0, 'Score' => 0];
                }
                self::$cache->cache_value($key, $this->groupVote, 259200); // 3 days
            }
        }
        return $this->groupVote;
    }

    /**
     * Returns an array with User Vote data: GroupID and vote type
     * @return array [groupId => 0|1]
     */
    public function userVotes() {
        $key = sprintf(self::VOTED_USER, $this->userId);
        if (($votes = self::$cache->get_value($key)) === false) {
            self::$db->prepared_query("
                SELECT GroupID,
                    CASE WHEN Type = 'Up' THEN 1 ELSE 0 END AS vote
                FROM users_votes
                WHERE UserID = ?
                ", $this->userId
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
    public function upvote() {
        return $this->castVote(1);
    }

    /**
     * The user downvotes a release
     *
     * @return array [bool success, string reason]
     */
    public function downvote() {
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
                   (UserID, GroupID, Type)
            VALUES (?,      ?,       ?)
            ", $this->userId, $this->groupId, $up ? 'Up' : 'Down'
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
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->userId), $this->userVote, 259200); // 3 days
        self::$cache->cache_value(sprintf(self::VOTED_GROUP, $this->groupId), $this->groupVote, 259200);
        self::$cache->delete_value(sprintf(self::VOTE_PAIR_KEY, $this->groupId));

        return [true, 'voted'];
    }

    /**
     * Clear a vote on this release group
     */
    public function clear() {
        if (!isset($this->userVote[$this->groupId])) {
            return [false, 'not-voted'];
        }
        $up = $this->userVote[$this->groupId] === 1 ? 1 : 0;

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM users_votes
            WHERE UserID = ? AND GroupID = ?
            ", $this->userId, $this->groupId
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
        self::$cache->cache_value(sprintf(self::VOTE_USER_KEY, $this->userId), $this->userVote, 259200);
        self::$cache->cache_value(sprintf(self::VOTED_GROUP, $this->groupId), $this->groupVote, 259200);
        self::$cache->delete_value(sprintf(self::VOTE_PAIR_KEY, $this->groupId));

        return [true, 'cleared'];
    }
}
