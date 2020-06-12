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
     * @seealso postInfo()
     */
    public function __construct(int $id = 0) {
        parent::__construct();
        $this->forumId = $id;
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
            self::CACHE_TOC_MAIN,
            sprintf(self::CACHE_TOC_FORUM, $this->forumId)
        ]);
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
            $this->cache->expire_value("thread_{$TopicID}_catalogue_$i", 86400);
        }
        $this->cache->expire_value("thread_{$TopicID}_info", 86400);
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
        $numPosts = $this->db->scalar("
            SELECT NumPosts
            FROM forums_topics
            WHERE ID = ?
            ", $threadId
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
        $this->saveEdit($userId, $postId, $oldBody);
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
     * Get information about a post. The forumId can be recovered from a postId.
     *
     * @param int $postId The post
     * @return array [$body $author, $threadId, $forumId, $isLocked, $minClassWrite, $pageNumber, $noPoll?]
     */
    public function postInfo(int $postId) {
        return $this->db->row("
            SELECT
                p.Body,
                p.AuthorID,
                p.TopicID,
                t.ForumID,
                t.IsLocked,
                f.MinClassWrite,
                ceil(
                    (
                        SELECT count(*)
                        FROM forums_posts AS p2
                        WHERE p2.TopicID = p.TopicID AND p2.ID <= ?
                    ) / ?
                ) AS Page
            FROM forums_posts        AS p
            INNER JOIN forums_topics AS t ON (p.TopicID = t.ID)
            INNER JOIN forums        AS f ON (t.ForumID = f.ID)
            WHERE p.ID = ?
            ", $postId, POSTS_PER_PAGE, $postId
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
                SELECT cat.Name AS categoryName,
                    f.ID, f.Name, f.Description, f.NumTopics, f.NumPosts, f.LastPostTopicID, f.MinClassRead, f.MinClassWrite, f.MinClassCreate,
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
