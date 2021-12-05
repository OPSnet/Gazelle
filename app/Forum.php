<?php

namespace Gazelle;

class Forum extends Base {
    protected $forumId;

    const CACHE_TOC_MAIN    = 'forum_toc_main';
    const CACHE_TOC_FORUM   = 'forum_toc_%d';
    const CACHE_FORUM       = 'forum_%d';
    const CACHE_THREAD_INFO = 'thread_%d_info';
    const CACHE_CATALOG     = 'thread_%d_catalogue_%d';

    /**
     * Construct a Forum object
     *
     * @param int id The forum ID.
     */
    public function __construct(int $id) {
        $this->forumId = $id;
    }

    public function id(): int {
        return $this->forumId;
    }

    /**
     * Get the thread ID from a post ID.
     *
     * @param int id The post ID.
     * @return int The thread ID.
     */
    public function findThreadIdByPostId(int $postId): int {
        $threadId = self::$db->scalar("
            SELECT TopicID FROM forums_posts WHERE ID = ?
            ", $postId
        );
        if (is_null($threadId)) {
            throw new \Gazelle\Exception\ResourceNotFoundException($postId);
        }
        return $threadId;
    }

    /**
     * Modify a forum
     * @param array hash of values (keyed on lowercase column names)
     */
    public function modify(array $args) {
        $autolock = isset($_POST['autolock']) ? '1' : '0';
        self::$db->prepared_query("
            UPDATE forums SET
                Sort           = ?,
                CategoryID     = ?,
                Name           = ?,
                Description    = ?,
                MinClassRead   = ?,
                MinClassWrite  = ?,
                MinClassCreate = ?,
                AutoLock       = ?,
                AutoLockWeeks  = ?
            WHERE ID = ?
            ", (int)$args['sort'], (int)$args['categoryid'], trim($args['name']), trim($args['description']),
               (int)$args['minclassread'], (int)$args['minclasswrite'], (int)$args['minclasscreate'],
               isset($args['autolock']) ? '1' : '0', (int)$args['autolockweeks'],
               $this->forumId
        );
        $this->flushCache();
    }

    /**
     * Remove a forum (There is no undo!)
     */
    public function remove() {
        self::$db->prepared_query("
            DELETE FROM forums
            WHERE ID = ?
            ", $this->forumId
        );
        $this->flushCache();
    }

    /**
     * Basic information about a forum
     *
     * @return array [forum_id, name, description, category_id,
     *      min_class_read, min_class_write, min_class_create, sequence, auto_lock, auto_lock_weeks]
     */
    public function info(): array {
        $key = sprintf(self::CACHE_FORUM, $this->forumId);
        if (($info = self::$cache->get_value($key)) === false) {
            $info = self::$db->rowAssoc("
                SELECT f.ID            AS forum_id,
                    f.Name             AS name,
                    f.Description      AS description,
                    f.CategoryID       AS category_id,
                    cat.Name           AS category_name,
                    f.MinClassRead     AS min_class_read,
                    f.MinClassWrite    AS min_class_write,
                    f.MinClassCreate   AS min_class_create,
                    f.NumTopics        AS num_threads,
                    f.NumPosts         AS num_posts,
                    f.LastPostID       AS last_post_id,
                    f.LastPostAuthorID AS last_author_id,
                    f.LastPostTopicID  AS last_thread_id,
                    ft.Title           AS last_thread,
                    f.LastPostTime     AS last_post_time,
                    f.Sort             AS sequence,
                    f.AutoLock         AS auto_lock,
                    f.AutoLockWeeks    AS auto_lock_weeks
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                LEFT JOIN forums_topics ft ON (ft.ID = f.LastPostTopicID)
                WHERE f.ID = ?
                ", $this->forumId
            );
            self::$cache->cache_value($key, $info, 86400);
        }
        return $info;
    }

    public function categoryId(): int {
        return $this->info()['category_id'];
    }

    public function categoryName(): string {
        return $this->info()['category_name'];
    }

    public function description(): string {
        return $this->info()['description'];
    }

    public function hasRevealVotes(): bool {
        return in_array($this->forumId, FORUM_REVEAL_VOTER);
    }

    public function isLocked(): bool {
        return $this->info()['is_locked'] ?? false;
    }

    public function isSticky(): bool {
        return $this->info()['is_sticky'] ?? false;
    }

    public function lastPostId(): int {
        return $this->info()['last_post_id'];
    }

    public function lastAuthorID(): ?int {
        return $this->info()['last_author_id'];
    }

    public function lastThread(): ?string {
        return $this->info()['last_thread'];
    }

    public function lastThreadId(): int {
        return $this->info()['last_thread_id'];
    }

    public function lastPostTime(): int {
        return $this->info()['last_post_time'] ? strtotime($this->info()['last_post_time']) : 0;
    }

    public function minClassCreate(): int {
        return $this->info()['min_class_create'];
    }

    public function minClassRead(): int {
        return $this->info()['min_class_read'];
    }

    public function minClassWrite(): int {
        return $this->info()['min_class_write'];
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function numPosts(): int {
        return $this->info()['num_posts'];
    }

    public function numThreads(): int {
        return $this->info()['num_threads'];
    }

    /* for the transition from sections/ to app/ - delete when done */
    public function flushCache() {
        (new \Gazelle\Manager\Forum)->flushToc();
        self::$cache->deleteMulti([
            sprintf(self::CACHE_FORUM, $this->forumId),
            sprintf(self::CACHE_TOC_FORUM, $this->forumId),
        ]);
    }

    /**
     * Add a thread to the forum
     *
     * @param int userID The author
     * @param string title The title of the thread
     * @param string body The body of the first post in thread
     * @return array $threadId The ID of the thread
     */
    public function addThread(int $userId, string $title, string $body) {
        // LastPostID is updated in updateTopic()
        $qid = self::$db->get_query_id();
        $db = new DB;
        $db->relaxConstraints(true);
        self::$db->prepared_query("
            INSERT INTO forums_topics
                   (ForumID, Title, AuthorID, LastPostAuthorID)
            Values (?,       ?,        ?,                ?)
            ", $this->forumId, $title, $userId, $userId
        );
        $threadId = self::$db->inserted_id();
        $body = trim($body);
        self::$db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, $body
        );
        $postId = self::$db->inserted_id();
        $this->updateTopic($userId, $threadId, $postId);
        $db->relaxConstraints(false);
        (new Stats\User($userId))->increment('forum_thread_total');
        self::$cache->cache_value(sprintf(self::CACHE_CATALOG, $threadId, 0), [
            $postId => [
                'ID'           => $postId,
                'AuthorID'     => $userId,
                'AddedTime'    => sqltime(),
                'Body'         => $body,
                'EditedUserID' => 0,
                'EditedTime'   => null,
            ]
        ]);
        self::$db->set_query_id($qid);
        return $threadId;
    }

    /**
     * Lock a thread. Closes its poll as a side-effect.
     *
     * @param int threadId The thread to lock
     */
    public function lockThread(int $threadId) {
        self::$db->prepared_query("
            UPDATE forums_polls SET
                Closed = '0'
            WHERE TopicID = ?
            ", $threadId
        );
        $max = self::$db->scalar("
            SELECT floor(NumPosts / ?) FROM forums_topics WHERE ID = ?
            ", THREAD_CATALOGUE, $threadId
        );
        for ($i = 0; $i <= $max; $i++) {
            self::$cache->delete_value(sprintf(self::CACHE_CATALOG, $threadId, $i));
        }
        self::$cache->delete_value(sprintf(self::CACHE_THREAD_INFO, $threadId));
        self::$cache->delete_value("polls_$threadId");
    }

    /**
     * Edit the metadata of a thread
     *
     * @param int threadId The thread to edit
     * @param int forumId Where is it being moved to?
     * @param int sticky Is the thread stuck at the top of the first page?
     * @param int rank Where in the list of sticky threads does it appear? (Larger numbers are higher)
     * @param int locked Is the thread locked?
     * @param string title The new title of the thread
     */
    public function editThread(int $threadId, int $forumId, int $sticky, int $rank, int $locked, string $title) {
        (new \Gazelle\Manager\Forum)->flushToc();
        self::$cache->deleteMulti([
            sprintf(self::CACHE_FORUM, $forumId),
            sprintf(self::CACHE_FORUM, $this->forumId),
            "zz_ft_{$threadId}", "thread_{$threadId}", sprintf(self::CACHE_THREAD_INFO, $threadId),
            sprintf(self::CACHE_TOC_FORUM, $this->forumId),
            sprintf(self::CACHE_TOC_FORUM, $forumId),
        ]);
        self::$db->prepared_query("
            UPDATE forums_topics SET
                ForumID  = ?,
                IsSticky = ?,
                Ranking  = ?,
                IsLocked = ?,
                Title    = ?
            WHERE ID = ?
            ", $forumId, $sticky ? '1' : '0', $rank, $locked ? '1' : '0', trim($title),
            $threadId
        );
        if ($forumId != $this->forumId) {
            $this->adjustForumStats($this->forumId);
            $this->adjustForumStats($forumId);
        }
        if ($locked) {
            $this->lockThread($threadId);
        }
    }

    /**
     * Remove a thread from the forum
     * Forum counters are updated, subscriptions tidied, user last-read markers are removed.
     *
     * @param int threadId The thread to remove
     */
    public function removeThread(int $threadId) {
        self::$db->prepared_query("
            DELETE ft, fp, unq
            FROM forums_topics AS ft
            LEFT JOIN forums_posts AS fp ON (fp.TopicID = ft.ID)
            LEFT JOIN users_notify_quoted as unq ON (unq.PageID = ft.ID AND unq.Page = 'forums')
            WHERE TopicID = ?
            ", $threadId
        );
        $this->adjustForumStats($this->forumId);

        (new Manager\Subscription)->move('forums', $threadId, null);

        (new Manager\Forum)->flushToc();
        self::$cache->deleteMulti([
            "thread_{$threadId}",
            sprintf(self::CACHE_FORUM, $this->forumId),
            sprintf(self::CACHE_THREAD_INFO, $threadId),
            sprintf(self::CACHE_TOC_FORUM, $this->forumId),
        ]);
    }

    /**
     * Adjust the number of topics and posts of a forum.
     * used when deleting a thread or moving a thread between forums.
     * Recalculates the last post details in case the move changes things.
     * NB: This uses a forumID passed in explicitly, because
     * moving threads requires two calls and it just makes things
     * a bit clearer.
     *
     * @param int forumId The ID of the forum
     */
    protected function adjustForumStats(int $forumId) {
        /* Recalculate the correct values from first principles.
         * This does not happen very often, and only a moderator
         * pays the cost. At least this way the number correct
         * themselves if ever they drift out of synch -- Spine
         */
        self::$db->prepared_query("
            UPDATE forums f
            LEFT JOIN (
                /* these are the values to update the row in the forums table */
                SELECT count(DISTINCT fp.ID) as NumPosts,
                    count(DISTINCT ft.ID) as NumTopics,
                    LAST.ID, LAST.TopicID, LAST.AuthorID, LAST.AddedTime,
                    ft.ForumID
                FROM forums_posts fp
                LEFT JOIN forums_topics ft on (ft.id = fp.topicid)
                LEFT JOIN (
                    /* find the most recent post of any thread in the forum */
                    SELECT p.ID, p.TopicID, p.AuthorID, p.AddedTime, t.ForumID
                    FROM forums_posts p
                    INNER JOIN forums_topics t ON (t.ID = p.TopicID)
                    WHERE t.ForumID = ?
                    ORDER BY p.AddedTime DESC
                    LIMIT 1
                ) AS LAST USING (ForumID)
                WHERE ft.ForumID = ?
            ) POST ON (POST.ForumID = f.ID)
            SET
                f.NumTopics        = coalesce(POST.NumTopics, 0),
                f.NumPosts         = coalesce(POST.NumPosts, 0),
                f.LastPostTopicID  = POST.TopicID,
                f.LastPostID       = POST.ID,
                f.LastPostAuthorID = POST.AuthorID,
                f.LastPostTime     = POST.AddedTime
            WHERE f.ID = ?
            ", $forumId, $forumId, $forumId
        );
        return self::$db->affected_rows();
    }

    /**
     * Add a post to a thread in the forum
     *
     * @param int userID The author
     * @param int threadId The thread
     * @param string body The body of the post
     * @return int postId The ID of the post
     */
    public function addPost(int $userId, int $threadId, string $body) {
        $qid = self::$db->get_query_id();
        self::$db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, trim($body)
        );
        $postId = self::$db->inserted_id();
        $this->updateTopic($userId, $threadId, $postId);
        (new Stats\User($userId))->increment('forum_post_total');
        self::$db->set_query_id($qid);
        $info = $this->threadInfo($threadId);
        $catId = (int)floor((POSTS_PER_PAGE * ceil($info['Posts'] / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE);
        self::$cache->deleteMulti(["thread_{$threadId}_catalogue_{$catId}", "thread_{$threadId}_info"]);
        return $postId;
    }

    /**
     * Sticky (or unsticky) a post in a thread
     *
     * @param int userID The stickier
     * @param int threadId The ID of the thread
     * @param int postId The ID of the post
     * @param bool set Sticky if true, otherwise unsticky
     */
    public function stickyPost(int $userId, int $threadId, int $postId, bool $set) {
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
            $threadId
        );
        if ($bottom === '') { // Gazelle null-to-string coercion sucks
            return;
        }
        $this->addThreadNote($threadId, $userId, "Post $postId " . ($set ? "stickied" : "unstickied"));
        self::$db->prepared_query("
            UPDATE forums_topics SET
                StickyPostID = ?
            WHERE ID = ?
            ", $set ? $postId : 0, $threadId
        );
        self::$cache->delete_value(sprintf(self::CACHE_THREAD_INFO, $threadId));
        for ($i = $bottom; $i <= $top; ++$i) {
            self::$cache->delete_value(sprintf(self::CACHE_CATALOG, $threadId, $i));
        }
    }

    /* How many posts precede this post in the thread? Used as a basis for
     * calculating on which page a post falls.
     */
    public function priorPostTotal(int $postId): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM forums_posts
            WHERE TopicID = (SELECT TopicID FROM forums_posts WHERE ID = ?)
                AND ID <= ?
            ", $postId, $postId
        );
    }

    /* Update the topic following the creation of a thread.
     * (Most recent thread and sets last poster).
     *
     * @param int userId The author
     * @param int threadId The thread
     * @param int postId The post in the thread
     */
    protected function updateTopic(int $userId, int $threadId, int $postId) {
        self::$db->prepared_query("
            UPDATE forums_topics SET
                NumPosts         = NumPosts + 1,
                LastPostTime     = now(),
                LastPostID       = ?,
                LastPostAuthorID = ?
            WHERE ID = ?
            ", $postId, $userId, $threadId
        );
        $this->updateRoot($userId, $threadId, $postId);
    }

    /**
     * Add a note to a thread
     *
     * @param int threadId The thread
     * @param int userId   The moderator
     * @param string notes  The multi-line text
     */
    public function addThreadNote(int $threadId, int $userId, string $notes) {
        self::$db->prepared_query("
            INSERT INTO forums_topic_notes
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, $notes
        );
    }

    /**
     * Get the notes of a thread
     *
     * @param int threadId the thread
     * @return array The notes [ID, AuthorID, AddedTime, Body]
     */
    public function threadNotes(int $threadId): array {
        self::$db->prepared_query("
            SELECT ID, AuthorID, AddedTime, Body
            FROM forums_topic_notes
            WHERE TopicID = ?
            ORDER BY ID ASC
            ", $threadId
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Update the forum catalog following a change to a thread.
     * (Most recent thread and poster).
     *
     * @param int userId The author
     * @param int threadId The thread
     * @param int postId The post in the thread
     */
    protected function updateRoot(int $userId, int $threadId, int $postId) {
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
            ", $this->forumId, $postId,  $userId, $threadId, $this->forumId
        );
        (new \Gazelle\Manager\Forum)->flushToc();
        self::$cache->deleteMulti([
            sprintf(self::CACHE_FORUM, $this->forumId),
            sprintf(self::CACHE_THREAD_INFO, $threadId),
            sprintf(self::CACHE_TOC_FORUM, $this->forumId)
        ]);
    }

    /**
     * Add a poll to a thread.
     * TODO: add a closing date
     *
     * @param int threadId The thread
     * @param string question The poll question
     * @param array answer An array of answers (between 2 and 25)
     * @param array vots An array of votes (1 per answer)
     */
    public function addPoll(int $threadId, string $question, array $answers, array $votes) {
        self::$db->prepared_query("
            INSERT INTO forums_polls
                   (TopicID, Question, Answers)
            VALUES (?,       ?,        ?)
            ", $threadId, $question, serialize($answers)
        );
        self::$cache->cache_value("polls_$threadId", [$question, $answers, $votes, null, '0'], 0);
    }

    /**
     * The answers for a poll
     *
     * @param int threadId The thread
     * @return array The answers
     */
    protected function fetchPollAnswers(int $threadId) {
        return unserialize(
            self::$db->scalar("
                SELECT Answers FROM forums_polls WHERE TopicID = ?
                ", $threadId
            )
        );
    }

    /**
     * Save the answers for a poll
     *
     * @param int threadId The thread
     * @param array answers The answers
     */
    protected function savePollAnswers(int $threadId, array $answers) {
        self::$db->prepared_query("
            UPDATE forums_polls SET
                Answers = ?
            WHERE TopicID = ?
            ", serialize($answers), $threadId
        );
        self::$cache->delete_value("polls_$threadId");
    }

    /**
     * Add a new answer to a poll.
     *
     * @param int threadId The thread
     * @param string answer The new answer
     */
    public function addPollAnswer(int $threadId, string $answer) {
        $answers = $this->fetchPollAnswers($threadId);
        $answers[] = $answer;
        $this->savePollAnswers($threadId, $answers);
    }

    /**
     * Remove an answer from a poll.
     *
     * @param int threadId The thread
     * @param int item The answer to remove (1-based)
     */
    public function removedPollAnswer(int $threadId, int $item) {
        $answers = $this->fetchPollAnswers($threadId);
        if ($answers) {
            unset($answers[$item]);
            $this->savePollAnswers($threadId, $answers);
            self::$db->prepared_query("
                DELETE FROM forums_polls_votes
                WHERE Vote = ?
                    AND TopicID = ?
                ", $item, $threadId
            );
            self::$cache->delete_value("polls_$threadId");
        }
    }

    /**
     * Vote on a poll
     *
     * @param int userId Who is voting?
     * @param int threadId Where are they voting?
     * @param int vote What are they voting for?
     * @return int 1 if this is the first time they have voted, otherwise 0
     */
    public function addPollVote(int $userId, int $threadId, int $vote) {
        self::$db->prepared_query("
            INSERT IGNORE INTO forums_polls_votes
                   (TopicID, UserID, Vote)
            VALUES (?,       ?,      ?)
            ", $threadId, $userId, $vote
        );
        $change = self::$db->affected_rows();
        if ($change) {
            self::$cache->delete_value("polls_$threadId");
        }
        return $change;
    }

    public function modifyPollVote(int $userId, int $threadId, int $vote): int {
        self::$db->prepared_query("
            UPDATE forums_polls_votes SET
                Vote = ?
            WHERE TopicID = ?
                AND UserID = ?
            ", $vote, $threadId, $userId
        );
        $change = self::$db->affected_rows();
        if ($change) {
            self::$cache->delete_value("polls_$threadId");
        }
        return $change;
    }

    /**
     * How did a person vote in a poll?
     *
     * @param int the user id
     * @param int the thread where the poll is defined
     * @return int vote (null if they have not voted)
     */
    public function pollVote(int $userId, int $threadId): ?int {
        return self::$db->scalar("
            SELECT Vote FROM forums_polls_votes WHERE UserID = ?  AND TopicID = ?
            ", $userId, $threadId
        );
    }

    /**
     * Get the data for a poll (TODO: spin off into a Poll object)
     *
     * @param int threadId The thread
     * @return array
     * - string: question
     * - array: answers
     * - votes: recorded votes
     * - featured: Featured on front page?
     * - closed: Not more voting possible
     */
    public function pollData(int $threadId) {
        if (![$Question, $Answers, $Votes, $Featured, $Closed] = self::$cache->get_value('polls_'.$threadId)) {
            [$Question, $Answers, $Featured, $Closed] = self::$db->row("
                SELECT Question, Answers, Featured, Closed
                FROM forums_polls
                WHERE TopicID = ?
                ", $threadId
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
                ", $threadId
            );
            $VoteArray = self::$db->to_array(false, MYSQLI_NUM);
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
            self::$cache->cache_value('polls_'.$threadId, [$Question, $Answers, $Votes, $Featured, $Closed], 0);
        }
        return [$Question, $Answers, $Votes, $Featured, $Closed];
    }

    public function pollDataExtended(int $threadId, int $userId): array {
        [$Question, $Answers, $Votes, $Featured, $Closed] = $this->pollData($threadId);
        $userVote = self::$db->scalar("
            SELECT Vote
            FROM forums_polls_votes
            WHERE Vote > 0
                AND UserID = ?
                AND TopicID = ?
            ", $userId, $threadId
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
     * Edit a poll
     * TODO: feature and unfeature a poll.
     *
     * @param int $threadId In which thread?
     * @param int $toFeature non-zero to feature on front page
     * @param int $toClose toggle open/closed for voting
     */
    public function moderatePoll(int $threadId, int $toFeature, int $toClose) {
        [$Question, $Answers, $Votes, $Featured, $Closed] = $this->pollData($threadId);
        if ($toFeature && !$Featured) {
            self::$db->prepared_query("
                UPDATE forums_polls SET
                    Featured = now()
                WHERE TopicID = ?
                ", $Featured, $threadId
            );
            $Featured = self::$db->scalar("
                SELECT Featured FROM forums_polls WHERE TopicID = ?
                ", $threadId
            );
            self::$cache->cache_value('polls_featured', $threadId ,0);
        }

        if ($toClose) {
            self::$db->prepared_query("
                UPDATE forums_polls SET
                    Closed = ?
                WHERE TopicID = ?
                ", $Closed ? '1' : '0', $threadId
            );
        }
        self::$cache->cache_value('polls_'.$threadId, [$Question, $Answers, $Votes, $Featured, $toClose], 0);
        self::$cache->delete_value('polls_'.$threadId);
    }

    /**
     * Get the names of the staff who voted per item in a poll
     *
     * @param int thread id
     * @return array of [concatenated user, vote]
     */
    public function staffVote(int $threadId): array {
        self::$db->prepared_query("
            SELECT group_concat(um.Username SEPARATOR ', '),
                fpv.Vote
            FROM users_main AS um
            INNER JOIN forums_polls_votes AS fpv ON (um.ID = fpv.UserID)
            WHERE fpv.TopicID = ?
            GROUP BY fpv.Vote
            ", $threadId
        );
        return self::$db->to_array(false, MYSQLI_NUM);
    }

    /**
     * Merge an addition to the last post in a thread
     *
     * @param int userID The editor making the change
     * @param int threadId The thread
     * @param string body The new contents
     */
    public function mergePost(int $userId, int $threadId, string $body) {
        [$postId, $oldBody] = self::$db->row("
            SELECT ID, Body
            FROM forums_posts
            WHERE TopicID = ?
                AND AuthorID = ?
            ORDER BY ID DESC LIMIT 1
            ", $threadId, $userId
        );

        // Edit the post
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = CONCAT(Body, '\n\n', ?),
                EditedTime = now()
            WHERE ID = ?
            ", $userId, trim($body), $postId
        );

        // Store edit history
        self::$db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page)
            VALUES (?,        ?,      ?,   'forums')
            ", $userId, $postId, $oldBody
        );
        self::$db->commit();

        // Update the cache
        $info = $this->threadInfo($threadId);
        self::$cache->deleteMulti([
            "edit_forum_" . $this->forumId,
            "thread_{$threadId}_info",
            "thread_{$threadId}_catalogue_"
                . (int)floor((POSTS_PER_PAGE * ceil($info['Posts'] / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE),
        ]);
        return $postId;
    }

    /**
     * Edit a post
     *
     * @param int userID The editor making the change
     * @param int postId The post
     * @param string body The new contents
     */
    public function editPost(int $userId, int $postId, string $body): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page)
            VALUES (?,        ?,      (SELECT Body from forums_posts WHERE ID = ?), 'forums')
            ", $userId, $postId, $postId
        );
        self::$db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = ?,
                EditedTime = now()
            WHERE ID = ?
            ", $userId, trim($body), $postId
        );
        self::$db->commit();

        $post = $this->postInfo($postId);
        self::$cache->deleteMulti([
            "edit_forum_$postId",
            "thread_{$post['thread-id']}_info",
            "thread_{$post['thread-id']}_catalogue_" . (int)floor((POSTS_PER_PAGE * $post['page'] - POSTS_PER_PAGE) / THREAD_CATALOGUE),
        ]);
        return self::$db->affected_rows();
    }

    /**
     * Get a post body. It is up to the calling code to ensure the viewer
     * has the permission to view it.
     *
     * @param int postId The post
     * @return string The post body or null if no such post
     */
    public function postBody(int $postId): ?string {
        return self::$db->scalar("
            SELECT p.Body
            FROM forums_posts AS p
            INNER JOIN forums_topics AS t ON (p.TopicID = t.ID)
            WHERE p.ID = ?
            ", $postId
        );
    }

    /**
     * Fetch information about a post. Note: The ForumID can be recovered from a PostID.
     *
     * @param int postId The post
     * @return array
     *   'min-class-write'  int Minimum permission required to write in forum where the post appears.
     *   'forum-id'         int forum ID
     *   'thread-id'        int thread ID
     *   'thread-locked'    int IS the thread locked? '0'/'1'
     *   'thread-pages'     int Number of pages in the thread
     *   'page'             int Page where the post falls
     *   'user-id'          int The user ID of the author
     *   'is-sticky'        int Is the post the sticky post of the thread?
     *   'body'             string The post contents
     *
     * NB: kebab-case chosen for grepability
     */
    public function postInfo(int $postId): array {
        return self::$db->rowAssoc("
            SELECT
                f.MinClassWrite         AS 'min-class-write',
                t.ForumID               AS 'forum-id',
                t.id                    AS 'thread-id',
                t.islocked              AS 'thread-locked',
                ceil(t.numposts / ?)    AS 'thread-pages' ,
                (SELECT ceil(sum(if(fp.ID <= p.ID, 1, 0)) / ?) FROM forums_posts fp WHERE fp.TopicID = t.ID)
                                        AS 'page',
                p.AuthorID              AS 'user-id',
                (p.ID = t.StickyPostID) AS 'is-sticky',
                p.Body                  AS 'body'
            FROM forums_topics      t
            INNER JOIN forums       f ON (t.forumid = f.id)
            INNER JOIN forums_posts p ON (p.topicid = t.id)
            WHERE p.ID = ?
            ", POSTS_PER_PAGE, POSTS_PER_PAGE, $postId
        ) ?? [];
    }

    /**
     * Remove a post from a thread
     *
     * @param int postID the ID of the post to remove
     * @return bool Success
     */
    public function removePost(int $postId) {
        $forumPost = $this->postInfo($postId);
        if (!$forumPost) {
            return false;
        }
        self::$db->prepared_query("
            DELETE fp, unq
            FROM forums_posts fp
            LEFT JOIN users_notify_quoted unq ON (unq.PostID = fp.ID and unq.Page = 'forums')
            WHERE fp.ID = ?
            ", $postId
        );
        if (self::$db->affected_rows() === 0) {
            return false;
        }
        $forumId  = $forumPost['forum-id'];
        $threadId = $forumPost['thread-id'];

        self::$db->prepared_query("
            UPDATE forums_topics t
            INNER JOIN
            (
                SELECT
                    count(p.ID) AS NumPosts,
                    IF(t.StickyPostID = ?, 0, t.StickyPostID) AS StickyPostID,
                    t.ID
                FROM forums_topics t
                LEFT JOIN forums_posts p ON (p.TopicID = t.ID) where t.id = ?
            ) UPD ON (UPD.ID = t.ID)
            LEFT JOIN (
                SELECT ID, AuthorID, AddedTime, TopicID
                FROM forums_posts
                WHERE TopicID = ?
                ORDER BY ID desc
                LIMIT 1
            ) LAST ON (LAST.TopicID = UPD.ID)
            SET
                t.NumPosts         = UPD.NumPosts,
                t.StickyPostID     = UPD.StickyPostID,
                t.LastPostID       = LAST.ID,
                t.LastPostAuthorID = LAST.AuthorID,
                t.LastPostTime     = LAST.AddedTime
            WHERE t.ID = ?
            ", $postId, $threadId, $threadId, $threadId
        );

        $this->adjustForumStats($forumId);

        (new \Gazelle\Manager\Subscription)->flush('forums', $threadId);

        // We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
        $begin = (int)floor((POSTS_PER_PAGE * (int)$forumPost['page'] - POSTS_PER_PAGE) / THREAD_CATALOGUE);
        $end = (int)floor((POSTS_PER_PAGE * (int)$forumPost['thread-pages'] - POSTS_PER_PAGE) / THREAD_CATALOGUE);
        for ($i = $begin; $i <= $end; $i++) {
            self::$cache->delete_value(sprintf(self::CACHE_CATALOG, $threadId, $i));
        }
        self::$cache->deleteMulti([sprintf(self::CACHE_THREAD_INFO, $threadId)]);
        $this->flushCache();
        return true;
    }

    /**
     * Get information about a thread
     * TODO: check if ever NumPosts != Posts
     *
     * @param int threadId The thread
     * @return array [Title ForumId AuthorID LastPostAuthorID StickyPostID Ranking NumPosts isLocked isSticky NoPoll Posts LastPostTime StickyPost]
     */
    public function threadInfo(int $threadId): array {
        if (($info = self::$cache->get_value(sprintf(self::CACHE_THREAD_INFO, $threadId))) === false) {
            self::$db->prepared_query("
                SELECT t.Title,
                    t.ForumID,
                    t.AuthorID,
                    t.LastPostAuthorID,
                    t.StickyPostID,
                    t.Ranking,
                    t.NumPosts,
                    t.IsLocked = '1' AS isLocked,
                    t.IsSticky = '1' AS isSticky,
                    isnull(p.TopicID) AS NoPoll,
                    count(fp.id) AS Posts,
                    max(fp.AddedTime) as LastPostTime
                FROM forums_topics AS t
                INNER JOIN forums_posts AS fp ON (fp.TopicID = t.ID)
                LEFT JOIN forums_polls AS p ON (p.TopicID = t.ID)
                WHERE t.ID = ?
                GROUP BY t.ID
                ", $threadId
            );
            if (!self::$db->has_results()) {
                return [];
            }
            $info = self::$db->next_record(MYSQLI_ASSOC, false);
            if ($info['StickyPostID']) {
                $info['Posts']--;
                $info['StickyPost'] = self::$db->rowAssoc("
                    SELECT p.ID,
                        p.AuthorID,
                        p.AddedTime,
                        p.Body,
                        p.EditedUserID,
                        p.EditedTime
                    FROM forums_posts AS p
                    WHERE p.TopicID = ? AND p.ID = ?
                    ", $threadId, $info['StickyPostID']
                );
            }
            self::$cache->cache_value(sprintf(self::CACHE_THREAD_INFO, $threadId), $info, 86400);
        }
        return $info;
    }

    /**
     * The forum table of contents (the main /forums.php view)
     *
     * @return array
     *  - string category name "Community"
     *  containing an array of (one per forum):
     *    - int 'ID' Forum id
     *    - string 'Name' Forum name "The Lounge"
     *    - string 'Description' Forum description "The Lounge"
     *    - int 'NumTopics' Number of threads (topics)
     *    - int 'NumPosts' Number of posts (sum of posts of all threads)
     *    - int 'LastPostTopicID' Thread id of most recent post
     *    - int 'MinClassRead' Min class read     \
     *    - int 'MinClassWrite' Min class write   -+-- ACLs
     *    - int 'MinClassCreate' Min class create /
     *    - int 'Sort' Positional rank
     *    - bool 'AutoLock' if true, forum will lock after AutoLockWeeks of inactivity
     *    - int 'AutoLockWeeks' number of weeks for inactivity timer
     *    - string 'Title' Title of most recent thread
     *    - int 'LastPostAuthorID' User id of author of most recent post
     *    - int 'LastPostID' Post id of most recent post
     *    - timestamp 'LastPostTime' Date of most recent thread (creation or post)
     *    - int 'IsSticky' Last post is locked (0/1)
     *    - int 'IsLocked' Last post is sticky (0/1)
     */
    public function tableOfContentsMain() {
        if (!$toc = self::$cache->get_value(self::CACHE_TOC_MAIN)) {
            self::$db->prepared_query("
                SELECT cat.Name AS categoryName, cat.ID AS categoryId,
                    f.ID, f.Name, f.Description, f.NumTopics, f.NumPosts,
                    f.LastPostTopicID, f.MinClassRead, f.MinClassWrite, f.MinClassCreate,
                    f.Sort, f.AutoLock, f.AutoLockWeeks,
                    ft.Title, ft.LastPostAuthorID, ft.LastPostID, ft.LastPostTime, ft.IsSticky, ft.IsLocked
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                LEFT JOIN forums_topics ft ON (ft.ID = f.LastPostTopicID)
                ORDER BY cat.Sort, cat.Name, f.Sort, f.Name
            ");
            $toc = [];
            while ($row = self::$db->next_row(MYSQLI_ASSOC)) {
                $category = $row['categoryName'];
                unset($row['categoryName']);
                $row['AutoLock'] = ($row['AutoLock'] == '1');
                if (!isset($toc[$category])) {
                    $toc[$category] = [];
                }
                $toc[$category][] = $row;
            }
            self::$cache->cache_value(self::CACHE_TOC_MAIN, $toc, 86400 * 10);
        }
        return $toc;
    }

    /**
     * The number of topics in this forum
     *
     * return int Number of topics
     */
    public function topicCount() {
        $toc = $this->tableOfContentsForum();
        return current($toc)['threadCount'];
    }

    /**
     * The table of contents of a forum. Only the first page is cached,
     * the subsequent pages are regenerated on each pageview.
     *
     * @return array
     *    - int 'ID' Forum id
     *    - string 'Title' Thread name "We will snatch your unsnatched FLACs"
     *    - int 'AuthorID' User id of author
     *    - int 'AuthorID' Thread locked? '0'/'1'
     *    - int 'IsSticky' Thread sticky? '0'/'1'
     *    - int 'NumPosts' Number of posts in thread
     *    - int 'LastPostID' Post id of most recent post
     *    - timestamp 'LastPostTime' Date of most recent post
     *    - int 'LastPostAuthorID' User id of author of most recent post
     *    - int 'stickyCount' Number of sticky posts
     *    - int 'threadCount' Total number of threads in forum
     */
    public function tableOfContentsForum(int $page = 1) {
        $key = sprintf(self::CACHE_TOC_FORUM, $this->forumId);
        $forumToc = null;
        if ($page > 1 || ($page == 1 && !$forumToc = self::$cache->get_value($key))) {
            self::$db->prepared_query("
                SELECT ID, Title, AuthorID, IsLocked, IsSticky,
                    NumPosts, LastPostID, LastPostTime, LastPostAuthorID,
                    (SELECT count(*) from forums_topics WHERE IsSticky = '1' AND ForumID = ?) AS stickyCount,
                    (SELECT count(*) from forums_topics WHERE ForumID = ?) AS threadCount
                FROM forums_topics
                WHERE ForumID = ?
                ORDER BY Ranking DESC, IsSticky DESC, LastPostTime DESC
                LIMIT ?, ?
                ", $this->forumId, $this->forumId, $this->forumId, ($page - 1) * TOPICS_PER_PAGE, TOPICS_PER_PAGE
            );
            $forumToc = self::$db->to_array('ID', MYSQLI_ASSOC, false);
            if ($page == 1) {
                self::$cache->cache_value($key, $forumToc, 86400 * 10);
            }
        }
        return $forumToc;
    }

    public function departmentList(User $user): array {
        $cond = [];
        $args = [];
        $permittedForums = $user->permittedForums();
        if (!$permittedForums) {
            $cond[] = 'f.MinClassRead <= ?';
            $args[] = $user->classLevel();
        } else {
            $cond[] = '(f.MinClassRead <= ? OR f.ID IN (' . placeholders($permittedForums) . '))';
            $args = array_merge([$user->classLevel()], $permittedForums);
        }
        $forbiddenForums = $user->forbiddenForums();
        if ($forbiddenForums) {
            $cond[] = 'f.ID NOT IN (' . placeholders($forbiddenForums) . ')';
            $args = array_merge($args, $forbiddenForums);
        }
        self::$db->prepared_query("
            SELECT f.ID  AS forum_id,
                f.Name   AS name,
                sum(if(ft.LastPostId > coalesce(flrt.PostID, 0) AND C.last_read <= ft.LastPostTime, 1, 0)) AS unread,
                (f.id = origin.id) AS active
            FROM (SELECT coalesce((SELECT last_read FROM user_read_forum WHERE user_id = ?), '2015-01-01 00:00:00') AS last_read) AS C,
                forums_topics ft
            INNER JOIN forums f ON (f.id = ft.ForumID)
            INNER JOIN forums origin USING (CategoryID)
            LEFT JOIN forums_last_read_topics flrt ON (flrt.TopicID = ft.id AND flrt.UserID = ?)
            WHERE origin.ID = ?
                AND " . implode(' AND ', $cond) . "
            GROUP BY ft.ForumID
            ORDER BY f.Sort
            ", $user->id(), $user->id(), $this->forumId, ...$args
        );
        $departmentList = self::$db->to_array('forum_id', MYSQLI_ASSOC, false);
        return $departmentList;
    }

    /**
     * Get a catalogue of thread posts
     *
     * @param int thread id
     * @param int how many posts per page
     * @param int which page are we on
     * @return array [post_id, author_id, added_time, body, editor_user_id, edited_time]
     */
    public function threadCatalog(int $threadId, int $perPage, int $page): array {
        $chunk = (int)floor(($page - 1) * $perPage / THREAD_CATALOGUE);
        $key = sprintf(self::CACHE_CATALOG, $threadId, $chunk);
        if (!$catalogue = self::$cache->get_value($key)) {
            $thread = $this->threadInfo($threadId);
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
                ", $threadId, $thread['StickyPostID'], THREAD_CATALOGUE, $chunk * THREAD_CATALOGUE
            );
            $catalogue = self::$db->to_array(false, MYSQLI_ASSOC);
            if (!$thread['isLocked'] || $thread['isSticky']) {
                self::$cache->cache_value($key, $catalogue, 0);
            }
        }
        return $catalogue;
    }

    public function threadPage(int $threadId, int $perPage, int $page): array {
        return array_slice($this->threadCatalog($threadId, $perPage, $page),
            (($page - 1) * $perPage) % THREAD_CATALOGUE, $perPage, true
        );
    }

    /**
     * Mark the user as having read everything in the forum.
     *
     * @param int userId The ID of the user catching up
     */
    public function userCatchup(int $userId) {
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics
                   (UserID, TopicID, PostID)
            SELECT ?,       ID,      LastPostID
            FROM forums_topics
            WHERE ForumID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $userId, $this->forumId
        );
    }

    /**
     * Mark the user as having read everything in the thread.
     *
     * @param int userId The ID of the user catching up
     * @param int threadId The ID of the thread
     * @param int postId The ID of the last post the user has read
     */
    public function userCatchupThread(int $userId, int $threadId, int $postId) {
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics
                   (UserID, TopicID, PostID)
            VALUES (?,      ?,       ?)
            ON DUPLICATE KEY UPDATE
                PostID = ?
            ", $userId, $threadId, $postId, $postId
        );
    }

    /**
     * Return a list of which page the user has read up to
     *
     * @param int userId The user id reading the forum
     * @param int perPage The number of topics per page
     * @return array
     *  - int 'TopicID' The thread id
     *  - int 'PostID'  The post id
     *  - int 'Page'    The page number
     */
    public function userLastRead(int $userId, int $perPage) {
        self::$db->prepared_query("
            SELECT
                l.TopicID,
                l.PostID,
                CEIL((
                        SELECT count(*)
                        FROM forums_posts AS p
                        WHERE p.TopicID = l.TopicID
                            AND p.ID <= l.PostID
                    ) / ?
                ) AS Page
            FROM forums_last_read_topics AS l
            INNER JOIN forums_topics ft ON (ft.ID = l.TopicID)
            WHERE ft.ForumID = ?
                AND l.UserID = ?
            ", $perPage, $this->forumId, $userId
        );
        return self::$db->to_array('TopicID');
    }

    /**
     * Clear the "last read" markers of users in a thread.
     *
     * @param int id The thread ID.
     */
    public function clearUserLastRead(int $threadId) {
        self::$db->prepared_query("
            DELETE FROM forums_last_read_topics
            WHERE TopicID = ?
            ", $threadId
        );
    }

    /**
     * Return the last (highest previously read) post ID for a user in this thread.
     *
     * @param int userId The user ID.
     * @param int threadId The thread ID.
     * @param int The last read post id (or 0 if thread has never been read before)
     */
    public function userLastReadPost(int $userId, int $threadId): int {
        return self::$db->scalar("
            SELECT PostID FROM forums_last_read_topics WHERE UserID = ? AND TopicID = ?
            ", $userId, $threadId
        ) ?? 0;
    }

    /**
     * the number of posts up to the given post in the thread
     *
     * @param int threadId The thread ID
     * @param int postId The post ID
     * @param int stickyPostId If a sticky post, the count will be less one
     * @return int the number of posts
     */
    public function threadNumPosts(int $threadId, int $postId, bool $hasSticky) {
        $count = self::$db->scalar("
            SELECT count(*)
            FROM forums_posts
            WHERE TopicID = ?
                AND ID <= ?
            ", $threadId, $postId
        );
        return $hasSticky ? $count - 1 : $count;
    }
}
