<?php

namespace Gazelle;

class Forum extends Base {
    protected $forumId;

    const CACHE_TOC = 'forum_toc';

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
     * Set the forum ID.
     *
     * @param int $id The forum ID.
     */
    public function setForum(int $forumId) {
        $this->forumId = $forumId;
    }

    /**
     * Add a thread to the forum
     *
     * @param int $userID The author
     * @param string $title The title of the thread
     * @param string $body The body of the first post in thread
     * @return array [$threadId, $postId] The IDs of the thread and post
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

        $this->db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, trim($body)
        );
        $postId = $this->db->inserted_id();

        $this->updateTopic($userId, $threadId, $postId);
        $this->db->set_query_id($qid);
        return [$threadId, $postId];
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
        $this->db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $threadId, $userId, trim($body)
        );
        $postId = $this->db->inserted_id();
        $this->updateTopic($userId, $threadId, $postId);
        return $postId;
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
        $this->cache->delete_value(self::CACHE_TOC);
    }

    /**
     * Add a poll to a thread.
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
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = ?,
                EditedTime = now()
            WHERE ID = ?
            ", $userId, trim($body), $postId
        );
    }

    /**
     * Record the history following a post edit
     *
     * @param int $userID The editor making the change
     * @param int $postId The post
     * @param string $body The previous contents
     */
    public function saveEdit(int $userId, int $postId, string $body) {
        $this->db->prepared_query("
            INSERT INTO comments_edits
                   (PostID, EditUser, Body, EditTime, Page)
            VALUES (?,      ?,        ?,    now(),   'forums')
            ", $postId, $userId, trim($body)
        );
        $this->cache->delete_value("forums_edits_$PostID");
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
     *    - int forum id
     *    - string forum name "The Lounge"
     *    - string forum description "The Lounge"
     *    - int number of threads (topics)
     *    - int number of posts (sum of posts of all threads)
     *    - int min class read   \
     *    - int min class write   -- ACLs
     *    - int min class create /
     *    - int number of posts in most recent thread
     *    - string title of most recent thread
     *    - int user id of author of most recent post
     *    - int post id of most recent post
     *    - timestamp date of most recent thread (creation or post)
     *    - int last post is locked (0/1)
     *    - int last post is sticky (0/1)
     */
    public function tableOfContents() {
        if (!$toc = $this->cache->get_value(self::CACHE_TOC)) {
            $this->db->prepared_query("
                SELECT cat.Name AS categoryName,
                    f.ID, f.Name, f.Description, f.NumTopics, f.NumPosts, f.MinClassRead, f.MinClassWrite, f.MinClassCreate,
                    ft.Title, ft.LastPostAuthorID, ft.LastPostID, ft.LastPostTime, ft.IsSticky, ft.IsLocked
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                LEFT JOIN forums_topics ft ON (ft.ID = f.LastPostTopicID)
                ORDER BY cat.Sort, f.Sort
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
            $this->cache->cache_value(self::CACHE_TOC, $toc, 86400 * 10);
        }
        return $toc;
    }
}
