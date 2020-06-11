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
     * return a list of which page the user has read up to
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
}
