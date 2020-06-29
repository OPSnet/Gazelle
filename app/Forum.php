<?php

namespace Gazelle;

class Forum extends Base {
    protected $forumId;

    const CACHE_TOC_MAIN  = 'forum_toc_main';
    const CACHE_TOC_FORUM = 'forum_toc_%d';

    /**
     * Construct a Forum object
     *
     * @param int $id The forum ID. In some circumstances it may not be possible
     * to determine in advance, for instance sections/forums/takeedit.php
     * @see postInfo()
     */
    public function __construct(int $id = 0) {
        parent::__construct();
        $this->forumId = $id;
    }

    /**
     * Create a forum
     * @param array hash of values (keyed on lowercase column names)
     */
    public function create(array $args) {
        $this->db->prepared_query("
            INSERT INTO forums
                   (Sort, CategoryID, Name, Description, MinClassRead, MinClassWrite, MinClassCreate, AutoLock, AutoLockWeeks)
            VALUES (?,    ?,          ?,    ?,           ?,            ?,             ?,              ?,        ?)
            ", (int)$args['sort'], (int)$args['categoryid'], trim($args['name']), trim($args['description']),
               (int)$args['minclassread'], (int)$args['minclasswrite'], (int)$args['minclasscreate'],
               isset($args['autolock']) ? '1' : '0', (int)$args['autolockweeks']
        );
        $this->flushCache();
    }

    /**
     * Modify a forum
     * @param array hash of values (keyed on lowercase column names)
     */
    public function modify(array $args) {
        $autolock = isset($_POST['autolock']) ? '1' : '0';
        $this->db->prepared_query("
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
        $this->db->prepared_query("
            DELETE FROM forums
            WHERE ID = ?
            ", $this->forumId
        );
        $this->flushCache();
    }

    /**
     * Does the forum exist? (Did someone try to look at forumid = 982451653?)
     *
     * @param int $id The forum ID.
     * @return bool True if it exists
     */
    public function exists() {
        return 1 == $this->db->scalar("SELECT 1 FROM forums WHERE ID = ?", $this->forumId);
    }

    /* for the transition from sections/ to app/ - delete when done */
    public function flushCache() {
        $this->cache->deleteMulti([
            'forums_list',
            self::CACHE_TOC_MAIN,
            sprintf(self::CACHE_TOC_FORUM, $this->forumId)
        ]);
    }

    /**
     * Get list of forum names
     */
    public function nameList() {
        $this->db->prepared_query("
            SELECT ID, Name
            FROM forums
            ORDER BY Sort
        ");
        return $this->db->to_array();
    }

    /**
     * Get list of forums keyed by category
     */
    public function categoryList() {
        if (($categories = $this->cache->get_value('forums_categories')) === false) {
            $this->db->prepared_query("
                SELECT ID, Name
                FROM forums_categories
                ORDER BY Sort, Name
            ");
            $categories = [];
            while (list($id, $name) = $this->db->next_record(MYSQLI_NUM, false)) {
                $categories[$id] = $name;
            }
            $this->cache->cache_value('forums_categories', $categories, 0);
        }
        return $categories;
    }

    /**
     * Set the forum ID.
     *
     * @param int $id The forum ID.
     */
    public function setForum(int $forumId) {
        $this->forumId = $forumId;
    }

    /**
     * Get the minimum class that can read the forum
     *
     * @return int permissions class level
     */
    public function minClassRead() {
        return $this->db->scalar("
            SELECT MinClassRead FROM forums WHERE ID = ?
            ", $this->forumId
        );
    }

    /**
     * Set the forum ID from a thread ID.
     *
     * @param int $id The thread ID.
     * @return int The forum ID.
     */
    public function setForumFromThread(int $threadId) {
        if (!($forumId = $this->cache->get_value("thread_forum_" . $threadId))) {
            $forumId = $this->db->scalar("
                SELECT ForumID
                FROM forums_topics
                WHERE ID = ?
                ", $threadId
            );
            $this->cache->cache_value("thread_forum_" . $threadId, $forumId, 0);
        }
        return $this->forumId = $forumId;
    }

    /**
     * Add a thread to the forum
     *
     * @param int $userID The author
     * @param string $title The title of the thread
     * @param string $body The body of the first post in thread
     * @return array $threadId The ID of the thread
     */
    public function addThread(int $userId, string $title, string $body) {
        // LastPostID is updated in updateTopic()
        $qid = $this->db->get_query_id();
        $this->db->prepared_query("
            INSERT INTO forums_topics
                   (ForumID, Title, AuthorID, LastPostAuthorID)
            Values (?,       ?,        ?,                ?)
            ", $this->forumId, $title, $userId, $userId
        );
        $threadId = $this->db->inserted_id();
        $body = trim($body);
        $this->db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, $body
        );
        $postId = $this->db->inserted_id();
        $this->cache->cache_value("thread_{$threadId}_catalogue_0", [
            $postId => [
                'ID'           => $postId,
                'AuthorID'     => $userId,
                'AddedTime'    => sqltime(),
                'Body'         => $body,
                'EditedUserID' => 0,
                'EditedTime'   => null,
            ]
        ]);
        $this->updateTopic($userId, $threadId, $postId);
        $this->db->set_query_id($qid);
        return $threadId;
    }

    /**
     * Lock a thread. Closes its poll as a side-effect.
     *
     * @param int $threadId The thread to lock
     */
    public function lockThread(int $threadId) {
        $this->db->prepared_query("
            UPDATE forums_polls
            SET Closed = 0
            WHERE TopicID = ?
            ", $threadId
        );
        $max = $this->db->scalar("
            SELECT floor(NumPosts / ?) FROM forums_topics WHERE ID = ?
            ", THREAD_CATALOGUE, $threadId
        );
        for ($i = 0; $i <= $max; $i++) {
            $this->cache->delete_value("thread_{$TopicID}_catalogue_$i");
        }
        $this->cache->delete_value("thread_{$TopicID}_info");
        $this->cache->delete_value("polls_$TopicID");
    }

    /**
     * Edit the metadata of a thread
     *
     * @param int $threadId The thread to edit
     * @param int $forumId Where is it being moved to?
     * @param int $sticky Is the thread stuck at the top of the first page?
     * @param int $rank Where in the list of sticky threads does it appear? (Larger numbers are higher)
     * @param int $locked Is the thread locked?
     * @param string $title The new title of the thread
     */
    public function editThread(int $threadId, int $forumId, int $sticky, int $rank, int $locked, string $title) {
        $this->cache->deleteMulti([
            'forums_list', "forums_" . $forumId, "forums_" . $this->forumId, "thread_{$threadId}", "thread_{$threadId}_info",
            self::CACHE_TOC_MAIN,
            sprintf(self::CACHE_TOC_FORUM, $this->forumId),
            sprintf(self::CACHE_TOC_FORUM, $forumId),
        ]);
        $this->db->prepared_query("
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
     * @param int $threadId The thread to remove
     */
    public function removeThread(int $threadId) {
        $this->cache->deleteMulti([
            'forums_list', "forums_" . $this->forumId, "thread_{$threadId}", "thread_{$threadId}_info",
            self::CACHE_TOC_MAIN,
            sprintf(self::CACHE_TOC_FORUM, $this->forumId),
        ]);
        $this->db->prepared_query("
            DELETE ft, fp, unq
            FROM forums_topics AS ft
            LEFT JOIN forums_posts AS fp ON (fp.TopicID = ft.ID)
            LEFT JOIN users_notify_quoted as unq ON (unq.PageID = ft.ID AND unq.Page = 'forums')
            WHERE TopicID = ?
            ", $threadId
        );
        $this->adjustForumStats($this->forumId);

        // subscriptions
        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->flushQuotes('forums', $threadId);
        $subscription->move('forums', $threadId, null);
    }

    /**
     * Adjust the number of topics and posts of a forum.
     * used when deleting a thread or moving a thread between forums.
     * Recalculates the last post details in case the move changes things.
     * NB: This uses a forumID passed in explicitly, because
     * moving threads requires two calls and it just makes things
     * a bit clearer.
     *
     * @param int $forumId The ID of the forum
     */
    protected function adjustForumStats(int $forumId) {
        /* Recalculate the correct values from first principles.
         * This does not happen very often, and only a moderator
         * pays the cost. At least this way the number correct
         * themselves if ever they drift out of synch -- Spine
         */
        $this->db->prepared_query("
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
        return $this->db->affected_rows();
    }

    /**
     * Add a post to a thread in the forum
     *
     * @param int $userID The author
     * @param int $threadId The thread
     * @param string $body The body of the post
     * @return int $postId The ID of the post
     */
    public function addPost(int $userId, int $threadId, string $body) {
        $qid = $this->db->get_query_id();
        $this->db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, trim($body)
        );
        $postId = $this->db->inserted_id();
        $this->updateTopic($userId, $threadId, $postId);
        $this->db->set_query_id($qid);
        return $postId;
    }

    /**
     * Sticky (or unsticky) a post in a thread
     *
     * @param int $userID The stickier
     * @param int $threadId The ID of the thread
     * @param int $postId The ID of the post
     * @param bool $set Sticky if true, otherwise unsticky
     */
    public function stickyPost(int $userId, int $threadId, int $postId, bool $set) {
        // need to reset the post catalogues
        list($bottom, $top) = $this->db->row("
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
        if ($begin === '') { // Gazelle null-to-string coercion sucks
            return;
        }
        $this->addThreadNote($threadId, $userId, "Post $postId " . ($set ? "stickied" : "unstickied"));
        $this->db->prepared_query("
            UPDATE forums_topics SET
                StickyPostID = ?
            WHERE ID = ?
            ", $set ? $postId : 0, $threadId
        );
        $this->cache->delete_value("thread_{$threadId}_info");
        for ($i = $bottom; $i <= $top; ++$i) {
            $this->cache->delete_value("thread_{$threadId}_catalogue_{$i}");
        }
    }

    /* Update the topic following the creation of a thread.
     * (Most recent thread and sets last poster).
     *
     * @param int $userId The author
     * @param int $threadId The thread
     * @param int $postId The post in the thread
     */
    protected function updateTopic(int $userId, int $threadId, int $postId) {
        $this->db->prepared_query("
            UPDATE forums_topics SET
                NumPosts         = NumPosts + 1,
                LastPostID       = ?,
                LastPostAuthorID = ?,
                LastPostTime     = now()
            WHERE ID = ?
            ", $postId, $userId, $threadId
        );
        $this->updateRoot($userId, $threadId, $postId);
    }

    /**
     * Add a note to a thread
     *
     * @param int $threadId The thread
     * @param int $userId   The moderator
     * @param string $notes  The multi-line text
     */
    public function addThreadNote(int $threadId, int $userId, string $notes) {
        $this->db->prepared_query("
            INSERT INTO forums_topic_notes
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, $notes
        );
    }

    /**
     * Update the forum catalog following a change to a thread.
     * (Most recent thread and poster).
     *
     * @param int $userId The author
     * @param int $threadId The thread
     * @param int $postId The post in the thread
     */
    protected function updateRoot(int $userId, int $threadId, int $postId) {
        $this->db->prepared_query("
            UPDATE forums SET
                NumPosts         = NumPosts + 1,
                NumTopics        = NumTopics + 1,
                LastPostID       = ?,
                LastPostAuthorID = ?,
                LastPostTopicID  = ?,
                LastPostTime     = now()
            WHERE ID = ?
            ", $postId,  $userId, $threadId, $this->forumId
        );
        $this->cache->deleteMulti([
            "forums_list",
            "forums_{$threadId}",
            "thread_{$threadId}_info",
            self::CACHE_TOC_MAIN,
            sprintf(self::CACHE_TOC_FORUM, $this->forumId)
        ]);
    }

    /**
     * Add a poll to a thread.
     * TODO: add a closing date
     *
     * @param int $threadId The thread
     * @param string $question The poll question
     * @param array $answer An array of answers (between 2 and 25)
     */
    public function addPoll(int $threadId, string $question, array $answer) {
        $this->db->prepared_query("
            INSERT INTO forums_polls
                   (TopicID, Question, Answers)
            VALUES (?,       ?,        ?)
            ", $threadId, $question, serialize($answer)
        );
    }

    /**
     * The answers for a poll
     *
     * @param int $threadId The thread
     * @return array The answers
     */
    protected function fetchPollAnswers(int $threadId) {
        return unserialize(
            $this->db->scalar("
                SELECT Answers
                FROM forums_polls
                WHERE TopicID = ?
                ", $threadId
            )
        );
    }

    /**
     * Save the answers for a poll
     *
     * @param int $threadId The thread
     * @param array $answers The answers
     */
    protected function savePollAnswers(int $threadId, array $answers) {
        $this->db->prepared_query("
            UPDATE forums_polls SET
                Answers = ?
            WHERE TopicID = ?
            ", serialize($answers), $threadId
        );
        $this->cache->delete_value("polls_$threadId");
    }

    /**
     * Add a new answer to a poll.
     *
     * @param int $threadId The thread
     * @param string $answer The new answer
     */
    public function addPollAnswer(int $threadId, string $answer) {
        $answers = $this->fetchPollAnswers($threadId);
        $answers[] = $answer;
        $this->savePollAnswers($threadId, $answers);
    }

    /**
     * Remove an answer from a poll.
     *
     * @param int $threadId The thread
     * @param int $item The answer to remove (1-based)
     */
    public function removedPollAnswer(int $threadId, int $item) {
        $answers = $this->fetchPollAnswers($threadId);
        if ($answers) {
            unset($Answers[$item]);
            $this->savePollAnswers($threadId, $answers);
            $this->prepared_query("
                DELETE FROM forums_polls_votes
                WHERE Vote = ?
                    AND TopicID = ?
                ", $item, $threadId
            );
            $this->cache->delete_value("polls_$ThreadID");
        }
    }

    /**
     * Get the data for a poll (TODO: spin off into a Poll object)
     *
     * @param int $threadId The thread
     * @return array
     * - string: question
     * - array: answers
     * - votes: recorded votes
     * - featured: Featured on front page?
     * - closed: Not more voting possible
     */
    public function pollData(int $threadId) {
        if (!list($Question, $Answers, $Votes, $Featured, $Closed) = $this->cache->get_value('polls_'.$threadId)) {
            list($Question, $Answers, $Featured, $Closed) = $this->db->row("
                SELECT Question, Answers, Featured, Closed
                FROM forums_polls
                WHERE TopicID = ?
                ", $threadId
            );
            if ($Featured == '') {
                $Featured = null;
            }
            $Answers = unserialize($Answers);
            $this->db->prepared_query("
                SELECT Vote, count(*)
                FROM forums_polls_votes
                WHERE Vote != '0'
                    AND TopicID = ?
                GROUP BY Vote
                ", $threadId
            );
            $VoteArray = $this->db->to_array(false, MYSQLI_NUM);
            $Votes = [];
            foreach ($VoteArray as $VoteSet) {
                list($Key,$Value) = $VoteSet;
                $Votes[$Key] = $Value;
            }
            for ($i = 1, $end = count($Answers); $i <= $end; ++$i) {
                if (!isset($Votes[$i])) {
                    $Votes[$i] = 0;
                }
            }
            $this->cache->cache_value('polls_'.$threadId, [$Question, $Answers, $Votes, $Featured, $Closed], 0);
        }
        return [$Question, $Answers, $Votes, $Featured, $Closed];
    }

    /**
     * Vote on a poll
     *
     * @param int $userId Who is voting?
     * @param int $threadId Where are they voting?
     * @param int $vote What are they voting for?
     * @return int 1 if this is the first time they have voted, otherwise 0
     */
    public function addPollVote(int $userId, int $threadId, int $vote) {
        $this->db->prepared_query("
            INSERT IGNORE INTO forums_polls_votes
                   (TopicID, UserID, Vote)
            VALUES (?,       ?,      ?)
            ", $threadId, $userId, $vote
        );
        $change = $this->db->affected_rows();
        if ($change) {
            $this->cache->delete_value("polls_$threadId");
        }
        return $change;
    }

    /**
     * Edit a poll
     * TODO: feature and unfeature a poll.
     *
     * @param int $threadId In which thread?
     * @param int $toFeature non-zero to feature on front page
     * @param int $toClose toggle open/closed for voting
     (*/
    public function moderatePoll(int $threadId, int $toFeature, int $toClose) {
        list($Question, $Answers, $Votes, $Featured, $Closed) = $this->pollData($threadId);
        if ($toFeature && !$Featured) {
            $Featured = sqltime();
            $this->db->prepared_query("
                UPDATE forums_polls SET
                    Featured = ?
                WHERE TopicID = ?
                ", $Featured, $threadId
            );
            $this->cache->cache_value('polls_featured', $threadId ,0);
        }

        if ($toClose) {
            $this->db->prepared_query("
                UPDATE forums_polls SET
                    Closed = ?
                WHERE TopicID = ?
                ", !$Closed, $threadId
            );
        }
        $this->cache->cache_value('polls_'.$threadId, [$Question, $Answers, $Votes, $Featured, $toClose], 0);
        $this->cache->delete_value('polls_'.$threadId);
    }

    /**
     * Merge an addition to the last post in a thread
     *
     * @param int $userID The editor making the change
     * @param int $threadId The thread
     * @param string $body The new contents
     */
    public function mergePost(int $userId, int $threadId, string $body) {
        list($postId, $oldBody) = $this->db->row("
            SELECT ID, Body
            FROM forums_posts
            WHERE TopicID = ?
                AND AuthorID = ?
            ORDER BY ID DESC LIMIT 1
            ", $threadId, $userId
        );

        // Edit the post
        $this->db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = CONCAT(Body, '\n\n', ?),
                EditedTime = now()
            WHERE ID = ?
            ", $userId, trim($body), $postId
        );

        // Store edit history
        $this->db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page, EditTime)
            VALUES (?,        ?,      ?,   'forums', now())
            ", $userId, $postId, $oldBody
        );
        return $postId;
    }

    /**
     * Edit a post
     *
     * @param int $userID The editor making the change
     * @param int $postId The post
     * @param string $body The new contents
     */
    public function editPost(int $userId, int $postId, string $body) {
        $this->db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page, EditTime)
            VALUES (?,        ?,      (SELECT Body from forums_posts WHERE ID = ?), 'forums', now())
            ", $userId, $postId, $postId
        );
        $this->db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = ?,
                EditedTime = now()
            WHERE ID = ?
            ", $userId, trim($body), $postId
        );
        $this->cache->delete_value("forums_edits_$postId");
    }

    /**
     * Fetch information about a post. Note: The ForumID can be recovered from a PostID.
     *
     * @param int $postId The post
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
    public function postInfo(int $postId) {
        $this->db->prepared_query('
            SELECT
                f.MinClassWrite         AS "min-class-write",
                t.ForumID               AS "forum-id",
                t.id                    AS "thread-id",
                t.islocked              AS "thread-locked",
                ceil(t.numposts / ?)    AS "thread-pages" ,
                (SELECT ceil(sum(if(fp.ID <= p.ID, 1, 0)) / ?) FROM forums_posts fp WHERE fp.TopicID = t.ID)
                                        AS "page",
                p.AuthorID              AS "user-id",
                (p.ID = t.StickyPostID) AS "is-sticky",
                p.Body                  AS "body"
            FROM forums_topics      t
            INNER JOIN forums       f ON (t.forumid = f.id)
            INNER JOIN forums_posts p ON (p.topicid = t.id)
            WHERE p.ID = ?
            ', POSTS_PER_PAGE, POSTS_PER_PAGE, $postId
        );
        return $this->db->next_record(MYSQLI_ASSOC, false);
    }

    /**
     * Remove a post from a thread
     *
     * @param int $postID the ID of the post to remove
     * @return bool Success
     */
    public function removePost(int $postId) {
        $forumPost = $this->postInfo($postId);
        if (!$forumPost) {
            return false;
        }
        $this->db->prepared_query("
            DELETE fp, unq
            FROM forums_posts fp
            LEFT JOIN users_notify_quoted unq ON (unq.PostID = fp.ID and unq.Page = 'forums')
            WHERE fp.ID = ?
            ", $postId
        );
        if ($this->db->affected_rows() == 0) {
            return false;
        }
        $forumId  = $forumPost['forum-id'];
        $threadId = $forumPost['thread-id'];

        $this->db->prepared_query("
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

        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->flush('forums', $threadId);
        $subscription->flushQuotes('forums', $threadId);

        // We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
        $begin = (int)floor((POSTS_PER_PAGE * (int)$forumPost['page'] - POSTS_PER_PAGE) / THREAD_CATALOGUE);
        $end = (int)floor((POSTS_PER_PAGE * (int)$forumPost['thread-pages'] - POSTS_PER_PAGE) / THREAD_CATALOGUE);
        for ($i = $begin; $i <= $end; $i++) {
            $this->cache->delete_value("thread_{$threadId}_catalogue_{$i}");
        }
        $this->cache->deleteMulti(["thread_{$threadId}_info", 'forums_list', "forums_$forumId"]);
        $this->flushCache();
        return true;
    }

    /**
     * Get information about a thread
     *
     * @param int $threadId The thread
     * @return array [$author, $isLocked, $isSticky, $numPosts, $noPoll?]
     */
    public function threadInfo(int $threadId) {
        return $this->db->row("
            SELECT
                f.AuthorID,
                f.IsLocked,
                f.IsSticky,
                f.NumPosts,
                ISNULL(p.TopicID) AS NoPoll
            FROM forums_topics     AS f
            LEFT JOIN forums_polls AS p ON (p.TopicID = f.ID)
            WHERE f.ID = ?
            ", $threadId
        );
    }

    /**
     * Get extended information about a thread
     * TODO: merge with threadInfo()
     *
     * @param int $threadId The thread
     * @return array [$author, $isLocked, $isSticky, $numPosts, $noPoll?]
     */
    public function threadInfoExtended(int $threadId) {
        return $this->db->row("
            SELECT
                t.ForumID,
                f.Name,
                f.MinClassWrite,
                t.NumPosts AS Posts,
                t.AuthorID,
                t.Title,
                t.IsLocked,
                t.IsSticky,
                t.Ranking
            FROM forums_topics AS t
            INNER JOIN forums AS f ON (f.ID = t.ForumID)
            WHERE t.ID = ?
            ", $threadId
        );
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
        if (!$toc = $this->cache->get_value(self::CACHE_TOC_MAIN)) {
            $this->db->prepared_query("
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
            while ($row = $this->db->next_row(MYSQLI_ASSOC)) {
                $category = $row['categoryName'];
                unset($row['categoryName']);
                if (!isset($toc[$category])) {
                    $toc[$category] = [];
                    $toc['AutoLock'] = ($toc['AutoLock'] == '1');
                }
                $toc[$category][] = $row;
            }
            $this->cache->cache_value(self::CACHE_TOC_MAIN, $toc, 86400 * 10);
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
        if ($page > 1 || ($page == 1 && !$forumToc = $this->cache->get_value($key))) {
            $this->db->prepared_query("
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
            $forumToc = $this->db->to_array('ID', MYSQLI_ASSOC, false);
            if ($page == 1) {
                $this->cache->cache_value($key, $forumToc, 86400 * 10);
            }
        }
        return $forumToc;
    }


    /**
     * Mark the user as having read everything in the forum.
     *
     * @param int $userId The ID of the user catching up
     */
    public function userCatchup(int $userId) {
        $this->db->prepared_query("
            INSERT INTO forums_last_read_topics
                   (UserID, TopicID, PostID)
            SELECT ?,       ID,      LastPostID
            FROM forums_topics
            WHERE (LastPostTime > now() - INTERVAL 30 DAY OR IsSticky = '1')
                AND ForumID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $userId, $this->forumId
        );
    }

    /**
     * Return a list of which page the user has read up to
     *
     * @param int $userId The user id reading the forum
     * @param int $perPage The number of topics per page
     * @return array
     *  - int 'TopicID' The thread id
     *  - int 'PostID'  The post id
     *  - int 'Page'    The page number
     */
    public function userLastRead(int $userId, int $perPage) {
        $this->db->prepared_query("
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
        return $this->db->to_array('TopicID');
    }

    /**
     * Clear the "last read" markers of users in a thread.
     *
     * @param int $id The thread ID.
     */
    public function clearUserLastRead(int $threadId) {
        $this->db->prepared_query("
            DELETE FROM forums_last_read_topics
            WHERE TopicID = ?
            ", $threadId
        );
    }
}
