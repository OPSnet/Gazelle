<?php

namespace Gazelle;

class ForumThread extends BaseObject {

    const CACHE_KEY     = 'fthread_%d';
    const CACHE_CATALOG = 'fthread_cat_%d_%d';

    protected array $info;

    public function tableName(): string {
        return 'forums_topics';
    }

    public function flush() {
        (new Manager\Forum)->flushToc();
        self::$cache->deleteMulti([
            sprintf(self::CACHE_KEY, $this->id),
            sprintf(self::CACHE_CATALOG, $this->id, 0),
            sprintf(self::CACHE_CATALOG, $this->id, 
                (int)floor(
                    (POSTS_PER_PAGE * ceil($this->postTotal() / POSTS_PER_PAGE) - POSTS_PER_PAGE)
                    / THREAD_CATALOGUE
                )
            ),
        ]);
    }

    public function location(): string {
        return "forums.php?action=viewthread&threadid={$this->id}";
    }

    public function url(): string {
        return htmlentities($this->location());
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title()));
    }

    /**
     * Get information about a thread
     * TODO: check if ever NumPosts != Posts
     */
    public function info(): array {
        if (!empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT t.Title          AS title,
                    t.ForumID           AS forum_id,
                    t.AuthorID          AS author_id,
                    t.CreatedTime       AS created,
                    t.LastPostAuthorID  AS last_post_author_id,
                    t.StickyPostID      AS pinned_post_id,
                    t.Ranking           AS pinned_ranking,
                    t.NumPosts          AS posts_total_summary,
                    t.IsLocked = '1'    AS is_locked,
                    t.IsSticky = '1'    AS is_pinned,
                    isnull(p.TopicID)   AS no_poll,
                    count(fp.id)        AS post_total,
                    max(fp.AddedTime)   AS last_post_time
                FROM forums_topics AS t
                INNER JOIN forums_posts AS fp ON (fp.TopicID = t.ID)
                LEFT JOIN forums_polls AS p ON (p.TopicID = t.ID)
                WHERE t.ID = ?
                GROUP BY t.ID
                ", $this->id
            );
            if ($info) {
                if ($info['pinned_post_id']) {
                    $info['posts_total']--;
                }
                $info['pinned_post'] = self::$db->rowAssoc("
                    SELECT p.ID,
                        p.AuthorID,
                        p.AddedTime,
                        p.Body,
                        p.EditedUserID,
                        p.EditedTime
                    FROM forums_posts AS p
                    WHERE p.TopicID = ?
                        AND p.ID = ?
                    ", $this->id, $info['pinned_post_id']
                );
                self::$cache->cache_value($key, $info, 86400);
            }
        }
        $this->info = $info;
        return $this->info;
    }

    public function authorId(): int {
        return $this->info()['author_id'];
    }

    public function author(): User {
        return new User($this->authorId());
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function forumId(): int {
        return $this->info()['forum_id'];
    }

    public function forum(): Forum {
        return new Forum($this->forumId());
    }

    public function hasPoll(): bool {
        return !$this->info()['no_poll'];
    }

    public function isLocked(): bool {
        return (bool)$this->info()['is_locked'];
    }

    public function isPinned(): bool {
        return (bool)$this->info()['is_pinned'];
    }

    public function lastAuthorId(): int {
        return $this->info()['last_post_author_id'];
    }

    public function lastAuthor(): User {
        return new User($this->lastAuthorId());
    }

    public function lastPostTime(): int {
        return $this->info()['last_post_time'];
    }

    public function pinnedPostId(): ?int {
        return $this->info()['pinned_post_id'];
    }

    public function pinnedPostInfo(): array {
        return $this->info()['pinned_post'];
    }

    public function pinnedRanking(): int {
        return $this->info()['pinned_ranking'];
    }

    public function postTotal(): int {
        return $this->info()['post_total'];
    }

    public function postTotalSummary(): int {
        return $this->info()['post_total_summary'];
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function slice(int $page, int $perPage): array {
        // Cache catalogue from which the page is selected, allows block caches and future ability to specify posts per page
        $catId = (int)floor(($perPage * ($page - 1)) / THREAD_CATALOGUE);
        $key = sprintf(self::CACHE_CATALOG, $this->id, $catId);
        $catalogue = self::$cache->get_value($key);
        if ($catalogue === false) {
            self::$db->prepared_query("
                SELECT p.ID,
                    p.AuthorID,
                    p.AddedTime,
                    p.Body,
                    p.EditedUserID,
                    p.EditedTime
                FROM forums_posts AS p
                WHERE p.TopicID = ?
                    AND p.ID != ?
                LIMIT ? OFFSET ?
                ", $this->id, $this->pinnedPostId(), THREAD_CATALOGUE, $catId * THREAD_CATALOGUE
            );
            $catalogue = self::$db->to_array(false, MYSQLI_ASSOC);
            if (!$this->isLocked() || $this->isPinned()) {
                self::$cache->cache_value($key, $catalogue, 0);
            }
        }
        return array_slice($catalogue, ($perPage * ($page - 1)) % THREAD_CATALOGUE, $perPage, true);
    }

    public function addPost(int $userId, string $body): int {
        self::$db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $this->id, $userId, trim($body)
        );
        $postId = self::$db->inserted_id();

        $this->info();
        $this->info['last_post_id']        = $postId;
        $this->info['last_post_author_id'] = $userId;

        $this->updateThread($userId, $postId);
        $this->flush();
        $this->forum()->flush();
        (new Stats\User($userId))->increment('forum_post_total');
        return $postId;
    }

    public function mergePost(int $userId, string $body): int {
        [$postId, $oldBody] = self::$db->row("
            SELECT ID, Body
            FROM forums_posts
            WHERE TopicID = ?
                AND AuthorID = ?
            ORDER BY ID DESC LIMIT 1
            ", $this->id, $userId
        );

        // Edit the post
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = CONCAT(Body, '\n\n', ?),
                EditedTime = now()
            WHERE ID = ?
            ", $userId, $body, $postId
        );

        // Store edit history
        self::$db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page)
            VALUES (?,        ?,      ?,   'forums')
            ", $userId, $postId, $oldBody
        );
        self::$db->commit();

        $this->updateThread($userId, $postId);
        $this->flush();
        $this->forum()->flush();
        return $postId;
    }


    public function editThread(int $forumId, bool $pinned, int $rank, bool $locked, string $title): int {
        self::$db->prepared_query("
            UPDATE forums_topics SET
                ForumID  = ?,
                IsSticky = ?,
                Ranking  = ?,
                IsLocked = ?,
                Title    = ?
            WHERE ID = ?
            ", $forumId, $pinned ? '1' : '0', $rank, $locked ? '1' : '0', trim($title),
            $this->id
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            if ($locked) {
                $this->lockThread();
            }
            if ($forumId != $this->forumId()) {
                $forum = new Forum($forumId);
                $forum->adjustForumStats($forumId);
                $forum->flush();
            }
            $this->forum()->adjustForumStats($forumId);
            $this->updateRoot(
                ...self::$db->row("
                    SELECT AuthorID, ID
                    FROM forums_posts
                    WHERE TopicID = ?
                    ORDER BY ID DESC
                    LIMIT 1
                    ", $this->id
                )
            );
            $this->flush();
        }
        return $affected;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE ft, fp, unq
            FROM forums_topics AS ft
            LEFT JOIN forums_posts AS fp ON (fp.TopicID = ft.ID)
            LEFT JOIN users_notify_quoted as unq ON (unq.PageID = ft.ID AND unq.Page = 'forums')
            WHERE TopicID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $this->forum()->adjustForumStats($this->forumId());

        (new Manager\Subscription)->move('forums', $this->id, null);

        $this->updateRoot(
            ...self::$db->selectRow("
                SELECT AuthorID, ID
                FROM forums_posts
                WHERE TopicID = ?
                ORDER BY ID DESC
                LIMIT 1
                ", $this->id
            )
        );
        $this->forum()->flush();
        $this->flush();
        return $affected;
    }

    public function addThreadNote(int $userId, string $notes): int {
        self::$db->prepared_query("
            INSERT INTO forums_topic_notes
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $this->id, $userId, $notes
        );
        return self::$db->inserted_id();
    }

    public function threadNotes(): array {
        self::$db->prepared_query("
            SELECT ID, AuthorID, AddedTime, Body
            FROM forums_topic_notes
            WHERE TopicID = ?
            ORDER BY ID ASC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function lockThread(): int {
        self::$db->prepared_query("
            UPDATE forums_polls SET
                Closed = '0'
            WHERE TopicID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $max = self::$db->scalar("
            SELECT floor(NumPosts / ?) FROM forums_topics WHERE ID = ?
            ", THREAD_CATALOGUE, $this->id
        );
        for ($i = 1; $i <= $max; $i++) {
            self::$cache->delete_value(sprintf(self::CACHE_CATALOG, $this->id, $i));
        }
        $this->flush();
        self::$cache->delete_value("polls_{$this->id}");
        return $affected;
    }

    public function pinPost(int $userId, int $postId, bool $set): int {
        // need to reset the post catalogues
        [$bottom, $top] = self::$db->row("
            SELECT
                floor((ceil(count(*)               / ?) * ? - ?) / ?) AS bottom,
                floor((ceil(sum(if(ID <= ?, 1, 0)) / ?) * ? - ?) / ?) AS top
            FROM forums_posts
            WHERE TopicID = ?
            GROUP BY TopicID
            ",        POSTS_PER_PAGE, POSTS_PER_PAGE, POSTS_PER_PAGE, THREAD_CATALOGUE,
             $postId, POSTS_PER_PAGE, POSTS_PER_PAGE, POSTS_PER_PAGE, THREAD_CATALOGUE,
            $this->id
        );
        if ($bottom === '') { // Gazelle null-to-string coercion sucks
            return 0;
        }
        $this->addThreadNote($userId, "Post $postId " . ($set ? "pinned" : "unpinned"));
        self::$db->prepared_query("
            UPDATE forums_topics SET
                StickyPostID = ?
            WHERE ID = ?
            ", $set ? $postId : 0, $this->id
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        for ($i = $bottom; $i <= $top; ++$i) {
            self::$cache->delete_value(sprintf(self::CACHE_CATALOG, $this->id, $i));
        }
        return $affected;
    }

    protected function updateThread(int $userId, int $postId): int {
        self::$db->prepared_query("
            UPDATE forums_topics SET
                NumPosts         = NumPosts + 1,
                LastPostTime     = now(),
                LastPostID       = ?,
                LastPostAuthorID = ?
            WHERE ID = ?
            ", $postId, $userId, $this->id()
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->updateRoot($userId, $postId);
        }
        return $affected;
    }

    /**
     * Update the topic following the creation of a thread.
     * (Most recent thread and sets last poster).
     */
    protected function updateRoot(int $userId, int $postId): int {
        self::$db->prepared_query("
            UPDATE forums f
            INNER JOIN (
                SELECT ft.ForumID,
                    COUNT(DISTINCT ft.ID) as numThreads,
                    COUNT(fp.ID) as numPosts
                FROM forums_topics ft
                INNER JOIN forums_posts fp ON (fp.TopicID = ft.ID)
                WHERE ft.ForumID = ?
            ) STATS ON (STATS.ForumID = f.ID) SET
                f.NumTopics        = STATS.numThreads,
                f.NumPosts         = STATS.numPosts,
                f.LastPostID       = ?,
                f.LastPostAuthorID = ?,
                f.LastPostTopicID  = ?,
                f.LastPostTime     = now()
            WHERE f.ID = ?
            ", $this->forumId(), $postId,  $userId, $this->id, $this->forumId()
        );
        $affected = self::$db->affected_rows();
        (new \Gazelle\Manager\Forum)->flushToc();
        $this->forum()->flush();
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        return $affected;
    }

    /**
     * Add a poll to a thread.
     * TODO: add a closing date
     *
     * @param string $question The poll question
     * @param array $answers An array of answers (between 2 and 25)
     * @param array $votes An array of votes (1 per answer)
     * @return int poll id
     */
    public function addPoll(string $question, array $answers, array $votes): int {
        self::$db->prepared_query("
            INSERT INTO forums_polls
                   (TopicID, Question, Answers)
            VALUES (?,       ?,        ?)
            ", $this->id, $question, serialize($answers)
        );
        self::$cache->cache_value("polls_{$this->id}", [$question, $answers, $votes, null, '0'], 0);
        return self::$db->inserted_id();
    }

    public function hasRevealVotes(): bool {
        return $this->forum()->hasRevealVotes();
    }

    protected function fetchPollAnswers(): array {
        return unserialize(
            self::$db->scalar("
                SELECT Answers FROM forums_polls WHERE TopicID = ?
                ", $this->id
            )
        );
    }

    protected function savePollAnswers(array $answers): int {
        self::$db->prepared_query("
            UPDATE forums_polls SET
                Answers = ?
            WHERE TopicID = ?
            ", serialize($answers), $this->id
        );
        self::$cache->delete_value("polls_{$this->id}");
        return self::$db->affected_rows();
    }

    public function addPollAnswer(string $answer): int {
        $answers = $this->fetchPollAnswers($this->id);
        $answers[] = $answer;
        return $this->savePollAnswers($this->id, $answers);
    }

    public function removedPollAnswer(int $item): int {
        $answers = $this->fetchPollAnswers($this->id);
        if (!$answers) {
            return 0;
        }
        unset($answers[$item]);
        $this->savePollAnswers($this->id, $answers);
        self::$db->prepared_query("
            DELETE FROM forums_polls_votes
            WHERE Vote = ?
                AND TopicID = ?
            ", $item, $this->id
        );
        self::$cache->delete_value("polls_{$this->id}");
        return self::$db->affected_rows();
    }

    public function addPollVote(int $userId, int $vote): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO forums_polls_votes
                   (TopicID, UserID, Vote)
            VALUES (?,       ?,      ?)
            ", $this->id, $userId, $vote
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            self::$cache->delete_value("polls_{$this->id}");
        }
        return $affected;
    }

    public function modifyPollVote(int $userId, int $vote): int {
        self::$db->prepared_query("
            UPDATE forums_polls_votes SET
                Vote = ?
            WHERE TopicID = ?
                AND UserID = ?
            ", $vote, $this->id, $userId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            self::$cache->delete_value("polls_{$this->id}");
        }
        return $affected;
    }

    public function pollResponse(int $userId): ?int {
        return self::$db->scalar("
            SELECT Vote
            FROM forums_polls_votes
            WHERE UserID = ?
                AND TopicID = ?
            ", $userId, $this->id
        );
    }

    /**
     * Get the data for a poll (TODO: spin off into a Poll object)
     *
     * @return array
     * - string: question
     * - array: answers
     * - votes: recorded votes
     * - featured: Featured on front page?
     * - closed: Not more voting possible
     */
    public function pollData(): array {
        $key = "polls_{$this->id}";
        if (![$Question, $Answers, $Votes, $Featured, $Closed] = self::$cache->get_value($key)) {
            [$Question, $Answers, $Featured, $Closed] = self::$db->row("
                SELECT Question, Answers, Featured, Closed
                FROM forums_polls
                WHERE TopicID = ?
                ", $this->id
            );
            if ($Featured == '') {
                $Featured = null;
            }
            $Answers = unserialize($Answers);
            self::$db->prepared_query("
                SELECT Vote, count(*)
                FROM forums_polls_votes
                WHERE Vote != '0'
                    AND TopicID = ?
                GROUP BY Vote
                ", $this->id
            );
            $VoteArray = self::$db->to_array(false, MYSQLI_NUM, false);
            $Votes = [];
            foreach ($VoteArray as $VoteSet) {
                [$Key,$Value] = $VoteSet;
                $Votes[$Key] = $Value;
            }
            for ($i = 1, $end = count($Answers); $i <= $end; ++$i) {
                if (!isset($Votes[$i])) {
                    $Votes[$i] = 0;
                }
            }
            self::$cache->cache_value($key, [$Question, $Answers, $Votes, $Featured, $Closed], 0);
        }
        return [$Question, $Answers, $Votes, $Featured, $Closed];
    }

    public function pollDataExtended(int $userId): array {
        [$Question, $Answers, $Votes, $Featured, $Closed] = $this->pollData($this->id);
        $userVote = self::$db->scalar("
            SELECT Vote
            FROM forums_polls_votes
            WHERE Vote > 0
                AND UserID = ?
                AND TopicID = ?
            ", $userId, $this->id
        );
        $max   = max($Votes) ?? 0;
        $total = array_sum($Votes ?? []);
        $tally = [];
        foreach ($Votes as $key => $score) {
            $tally[$key] = [
                'answer'  => $Answers[$key],
                'score'   => $score,
                'ratio'   => $total ? ($score /   $max) * 100.0 : 0.0,
                'percent' => $total ? ($score / $total) * 100.0 : 0.0,
            ];
        }
        return [
            'question'    => $Question,
            'is_closed'   => (bool)$Closed,
            'is_featured' => (bool)$Featured,
            'tally'       => $tally,
            'user_vote'   => $userVote,
            'votes_max'   => $max,
            'votes_total' => $total,
        ];
    }

    /**
     * TODO: feature and unfeature a poll.
     */
    public function moderatePoll(int $toFeature, int $toClose): int {
        [$Question, $Answers, $Votes, $Featured, $Closed] = $this->pollData($this->id);
        $affected = 0;
        if ($toFeature && !$Featured) {
            self::$db->prepared_query("
                UPDATE forums_polls SET
                    Featured = now()
                WHERE TopicID = ?
                ", $this->id
            );
            $affected += self::$db->affected_rows();
            $Featured = self::$db->scalar("
                SELECT Featured FROM forums_polls WHERE TopicID = ?
                ", $this->id
            );
            self::$cache->cache_value('polls_featured', $this->id ,0);
        }

        if ($toClose) {
            self::$db->prepared_query("
                UPDATE forums_polls SET
                    Closed = ?
                WHERE TopicID = ?
                ", $Closed ? '1' : '0', $this->id
            );
            $affected += self::$db->affected_rows();
        }
        self::$cache->cache_value('polls_'.$this->id, [$Question, $Answers, $Votes, $Featured, $toClose], 0);
        self::$cache->delete_value('polls_'.$this->id);
        return $affected;
    }

    public function staffVote(): array {
        self::$db->prepared_query("
            SELECT group_concat(um.Username SEPARATOR ', '),
                fpv.Vote
            FROM users_main AS um
            INNER JOIN forums_polls_votes AS fpv ON (um.ID = fpv.UserID)
            WHERE fpv.TopicID = ?
            GROUP BY fpv.Vote
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_NUM, false);
    }

    /**
     * Get a catalogue of thread posts
     *
     * @return array [post_id, author_id, added_time, body, editor_user_id, edited_time]
     */
    public function threadCatalog(int $perPage, int $page): array {
        $chunk = (int)floor(($page - 1) * $perPage / THREAD_CATALOGUE);
        $key = sprintf(self::CACHE_CATALOG, $this->id, $chunk);
        $catalogue = self::$cache->get_value($key);
        if ($catalogue === false) {
            self::$db->prepared_query("
                SELECT p.ID         AS post_id,
                    p.AuthorID      AS author_id,
                    p.AddedTime     AS added,
                    p.Body          AS body,
                    p.EditedUserID  AS edit_user_id
                FROM forums_posts AS p
                WHERE p.TopicID = ?
                    AND p.ID != ?
                LIMIT ? OFFSET ?
                ", $this->id, $this->pinnedPostId(), THREAD_CATALOGUE, $chunk * THREAD_CATALOGUE
            );
            $catalogue = self::$db->to_array(false, MYSQLI_ASSOC, false);
            if (!$thread->isLocked() || $thread->isPinned()) {
                self::$cache->cache_value($key, $catalogue, 0);
            }
        }
        return $catalogue;
    }

    public function catchup(int $userId, int $postId): int {
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics
                   (UserID, TopicID, PostID)
            VALUES (?,      ?,       ?)
            ON DUPLICATE KEY UPDATE
                PostID = ?
            ", $userId, $this->id, $postId, $postId
        );
        return self::$db->affected_rows();
    }

    public function clearUserLastRead(): int {
        self::$db->prepared_query("
            DELETE FROM forums_last_read_topics
            WHERE TopicID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Return the last (highest previously read) post ID for a user in this thread.
     */
    public function userLastReadPost(int $userId): int {
        return (int)self::$db->scalar("
            SELECT PostID FROM forums_last_read_topics WHERE UserID = ? AND TopicID = ?
            ", $userId, $this->id
        );
    }

    /**
     * The number of posts up to the given post in the thread
     * If $hasSticky is true, count will be one less.
     */
    public function lesserPostTotal(int $postId): int {
        return self::$db->scalar("
            SELECT count(*) FROM forums_posts WHERE TopicID = ? AND ID <= ?
            ", $this->id, $postId
        ) - (int)$this->isPinned();
    }
}
