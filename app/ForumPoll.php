<?php

namespace Gazelle;

class ForumPoll extends BaseObject {
    final public const tableName = 'forums_polls';
    final public const pkName    = 'TopicID';
    final protected const CACHE_KEY = 'forum_poll_%d';

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->id),
            Manager\ForumPoll::CACHE_FEATURED_POLL,
        ]);
        unset($this->info);
        return $this;
    }
    public function link(): string { return $this->thread()->link(); }
    public function location(): string { return "forums.php?action=viewthread&threadid={$this->id}"; }

    public function thread(): ForumThread {
        return new ForumThread($this->id);
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info !== false) {
            $this->info = $info;
            return $this->info;
        }
        $poll = self::$db->rowAssoc("
            SELECT Question,
                Answers,
                Featured,
                Closed
            FROM forums_polls
            WHERE TopicID = ?
            ", $this->id
        );
        $answerList = unserialize($poll['Answers']);

        self::$db->prepared_query("
            SELECT fpv.Vote,
                count(*) AS total
            FROM forums_polls_votes fpv
            WHERE fpv.Vote != '0'
                AND fpv.TopicID = ?
            GROUP BY fpv.Vote
            ", $this->id
        );
        $vote = self::$db->to_pair('Vote', 'total', false);

        $total = array_sum($vote);
        $max   = count($vote) ? max($vote) : 0;
        $tally = [];
        foreach ($answerList as $key => $answer) {
            if (isset($vote[$key])) {
                $tally[$key] = [
                    'answer'  => $answer,
                    'score'   => $vote[$key],
                    'ratio'   => ($vote[$key] /   $max) * 100.0,
                    'percent' => ($vote[$key] / $total) * 100.0,
                ];
            } else {
                $tally[$key] = [
                    'answer'  => $answer,
                    'score'   => 0,
                    'ratio'   => 0,
                    'percent' => 0,
                ];
            }
        }

        $info = [
            'is_closed'   => $poll['Closed'] != '0',
            'is_featured' => (bool)$poll['Featured'],
            'max'         => $max,
            'question'    => $poll['Question'],
            'total'       => $total,
            'vote'        => $tally,
        ];
        self::$cache->cache_value($key, $info, 86400);
        $this->info = $info;
        return $this->info;
    }

    public function isClosed(): bool {
        return $this->info()['is_closed'];
    }

    public function isFeatured(): bool {
        return $this->info()['is_featured'];
    }

    public function max(): int {
        return $this->info()['max'];
    }

    public function question(): string {
        return $this->info()['question'];
    }

    public function total(): int {
        return $this->info()['total'];
    }

    public function close(): static {
        return $this->setField('Closed', '1');
    }

    /**
     * The current tally of votes
     *
     * @return array hash keys by vote response with the following keys:
     *   'answer', 'score', 'ratio', 'percent'
     */
    public function vote(): array {
        return $this->info()['vote'];
    }

    public function hasRevealVotes(): bool {
        return $this->thread()->forum()->hasRevealVotes();
    }

    protected function answerList(): array {
        return array_map(fn($v) => $v['answer'], $this->info()['vote']);
    }

    protected function saveAnswerList(array $answerList): int {
        self::$db->prepared_query("
            UPDATE forums_polls SET
                Answers = ?
            WHERE TopicID = ?
            ", serialize($answerList), $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    /**
     * Add a new answer to an existing poll. Find the highest existing answer value
     * and save the new answer as highest + 1
     *
     * @return int 1 if the answer was added, otherwise 0
     */
    public function addAnswer(string $answer): int {
        $answerList = $this->answerList();
        return $this->saveAnswerList($answerList + [1 + max(array_keys($answerList)) => $answer]);
    }

    public function removeAnswer(int $item): int {
        $answerList = $this->answerList();
        if (!$answerList) {
            return 0;
        }
        unset($answerList[$item]);
        $affected = $this->saveAnswerList($answerList);
        self::$db->prepared_query("
            DELETE FROM forums_polls_votes
            WHERE TopicID = ?
                AND Vote = ?
            ", $this->id, $item
        );
        $this->flush();
        return $affected;
    }

    public function addVote(User $user, int $vote): int {
        $answer = $this->vote();
        if (!isset($answer[$vote]) && $vote != 0) {
            return 0;
        }
        self::$db->prepared_query("
            INSERT IGNORE INTO forums_polls_votes
                   (TopicID, UserID, Vote)
            VALUES (?,       ?,      ?)
            ", $this->id, $user->id(), $vote
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function modifyVote(User $user, int $vote): int {
        self::$db->prepared_query("
            UPDATE forums_polls_votes SET
                Vote = ?
            WHERE TopicID = ?
                AND UserID = ?
            ", $vote, $this->id, $user->id()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function response(User $user): ?int {
        $vote = self::$db->scalar("
            SELECT Vote
            FROM forums_polls_votes
            WHERE UserID = ?
                AND TopicID = ?
            ", $user->id(), $this->id
        );
        return $vote ? (int)$vote : null;
    }

    /**
     * Return the nominative results. Each vote response contains
     * an array 'who' of User objects who have voted for that response.
     * There is also a 'missing' key that lists all staff members who
     * have not yet voted on the poll.
     *
     * @param Manager\User $userMan a user manager object to hydrate user ids.
     * @return array of vites
     */
    public function staffVote(Manager\User $userMan): array {
        $vote = $this->vote();
        $vote['missing'] = ['who' => []];
        foreach ($vote as &$v) {
            $v['who'] = [];
        }
        unset($v);
        self::$db->prepared_query("
            SELECT um.ID AS user_id,
                fpv.Vote AS response
            FROM users_main AS um
            INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
            LEFT JOIN forums_polls_votes fpv ON (fpv.UserID = um.ID AND fpv.TopicID = ?)
            WHERE p.Level >= (SELECT Level FROM permissions WHERE ID = ?)
            ", $this->id, FORUM_MOD
        );
        $result = self::$db->to_pair('user_id', 'response', false);
        foreach ($result as $userId => $response) {
            $vote[$response ?? 'missing']['who'][] = $userMan->findById($userId);
        }
        return $vote;
    }
}
