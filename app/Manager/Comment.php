<?php

namespace Gazelle\Manager;

class Comment extends \Gazelle\Base {

    protected function className(string $page): string {
        switch ($page) {
            case 'artist':
                return '\\Gazelle\\Comment\\Artist';
            case 'collages':
                return '\\Gazelle\\Comment\\Collage';
            case 'requests':
                return '\\Gazelle\\Comment\\Request';
            case 'torrents':
                return '\\Gazelle\\Comment\\Torrent';
            default:
                throw new \Gazelle\Exception\InvalidCommentPageException($page);
        }
    }

    public function findById(int $postId) {
        [$page, $pageId] = $this->db->row("
            SELECT Page, PageID FROM comments WHERE ID = ?
            ", $postId
        );
        if (is_null($page)) {
            throw new \Gazelle\Exception\ResourceNotFoundException($postId);
        }
        $className = $this->className($page);
        return (new $className($pageId))->setPostId($postId);
    }

    /**
     * Post a comment on an artist, request or torrent page.
     * @param string $page
     * @param int $pageId
     * @param string $Body
     * @return int ID of the new comment
     */
    public function create(int $userId, string $page, int $pageId, string $body) {
        $this->db->prepared_query("
            INSERT INTO comments
                   (Page, PageID, AuthorID, Body)
            VALUES (?,    ?,      ?,        ?)
            ", $page, $pageId, $userId, $body
        );
        $postId = $this->db->inserted_id();
        $catalogueId = $this->db->scalar("
            SELECT floor((ceil(count(*) / ?) - 1) * ? / ?)
            FROM comments
            WHERE Page = ? AND PageID = ?
            ", TORRENT_COMMENTS_PER_PAGE, TORRENT_COMMENTS_PER_PAGE, THREAD_CATALOGUE, $page, $pageId
        );
        $this->cache->deleteMulti([
            "{$page}_comments_{$pageId}_catalogue_{$catalogueId}",
            "{$page}_comments_{$pageId}"
        ]);
        if ($page == 'collages') {
            $this->cache->delete_value("{$page}_comments_recent_{$pageId}");
        }
        $subscription = new Subscription($userId);
        $subscription->flush($page, $pageId);
        $subscription->quoteNotify($body, $postId, $page, $pageId);

        $className = $this->className($page);
        return (new $className($pageId))->setPostId($postId);
    }

    public function merge(string $page, int $pageId, int $targetPageId) {
        $qid = $this->db->get_query_id();

        $this->db->prepared_query("
            UPDATE comments SET
                PageID = ?
            WHERE Page = ? AND PageID = ?
            ", $targetPageId, $page, $pageId
        );
        $pageCount = $this->db->scalar("
            SELECT ceil(count(*) / ?) AS Pages
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $page, $targetPageId
        );
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $pageCount - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);

        // quote notifications
        $this->db->prepared_query("
            UPDATE users_notify_quoted SET
                PageID = ?
            WHERE Page = ? AND PageID = ?
            ", $targetPageId, $page, $pageId
        );
        $this->db->set_query_id($qid);

        // comment subscriptions
        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->move($page, $pageId, $targetPageId);

        for ($i = 0; $i <= $last; ++$i) {
            $this->cache->delete_value($page . "_comments_$targetPageId" . "_catalogue_$i");
        }
        $this->cache->delete_value($page."_comments_$targetPageId");
    }

    /**
     * Remove all comments on $page/$pageId (handle quote notifications and subscriptions as well)
     * @param string $page
     * @param int $pageId
     * @return boolean removal successful (or there was nothing to remove)
     */
    public function remove(string $page, int $pageId) {
        $qid = $this->db->get_query_id();

        $pageCount = $this->db->scalar("
            SELECT ceil(count(*) / ?) AS Pages
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $page, $pageId
        );
        if ($pageCount === 0) {
            return false;
        }
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $pageCount - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);

        $this->db->prepared_query("
            DELETE FROM comments WHERE Page = ? AND PageID = ?
            ", $page, $pageId
        );

        // Delete quote notifications
        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->move($page, $pageId, null);
        $subscription->flushQuotes($page, $pageId);

        $this->db->prepared_query("
            DELETE FROM users_notify_quoted WHERE Page = ? AND PageID = ?
            ", $page, $pageId
        );
        $this->db->set_query_id($qid);

        // Clear cache
        for ($i = 0; $i <= $last; ++$i) {
            $this->cache->delete_value($page . '_comments_' . $pageId . '_catalogue_' . $i);
        }
        $this->cache->delete_value($page . '_comments_' . $pageId);

        return true;
    }

    public function loadEdits(string $page, int $postId): array {
        $key = "edit_{$page}_{$postId}";
        $edits = $this->cache->get_value($key);
        if ($edits === false) {
            $this->db->prepared_query("
                SELECT EditUser, EditTime, Body
                FROM comments_edits
                WHERE Page = ?
                    AND PostID = ?
                ORDER BY EditTime DESC
                ", $page, $postId
            );
            $edits = $this->db->to_array(false, MYSQLI_NUM, false);
            $this->cache->cache_value($key, $edits, 0);
        }
        return $edits;
    }

    /**
     * Load recent collage comments. Used for displaying recent comments on collage pages.
     * @param int $CollageID ID of the collage
     * @return array ($Comments)
     *     $Comments
     *         ID: Comment ID
     *         Body: Comment body
     *         AuthorID: Author of comment
     *         Username: Their username
     *         AddedTime: Date of comment creation
     */
    public function collageSummary($collageId, $count = 5): array {
        $key = "collages_comments_recent_$collageId";
        $list = $this->cache->get_value($key);
        if ($list === false) {
            $qid = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT c.ID AS id,
                    c.Body as body,
                    c.AuthorID as author_id,
                    c.AddedTime as added
                FROM comments AS c
                LEFT JOIN users_main AS um ON (um.ID = c.AuthorID)
                WHERE c.Page = ? AND c.PageID = ?
                ORDER BY c.ID DESC
                LIMIT ?
                ", 'collages', $collageId, $count
            );
            $list = $this->db->to_array(false, MYSQLI_ASSOC, false);
            $this->db->set_query_id($qid);
            if (count($list)) {
                $this->cache->cache_value($key, $list, 7200);
            }
        }
        return $list;
    }

    /**
     * How many subscribed entities (artists, collages, requests, torrents)
     * have new comments on them?
     *
     * @param \Gazelle\User the viewer
     * @return int Number of entities with unread comments
     */
    public function unreadSubscribedCommentTotal(\Gazelle\User $user): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM users_subscriptions_comments AS s
            LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = s.UserID AND lr.Page = s.Page AND lr.PageID = s.PageID)
            LEFT JOIN comments AS c ON (c.ID = (SELECT MAX(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
            LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
            WHERE (s.Page != 'collages' OR co.Deleted = '0')
                AND coalesce(lr.PostID, 0) < c.ID
                AND s.UserID = ?
            ", $user->id()
        );
    }

    /**
     * How many total subscribed entities (artists, collages, requests, torrents)
     *
     * @param \Gazelle\User the viewer
     * @return int Number of entities
     */
    public function subscribedCommentTotal(\Gazelle\User $user): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM users_subscriptions_comments AS s
            LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = s.UserID AND lr.Page = s.Page AND lr.PageID = s.PageID)
            LEFT JOIN comments AS c ON (c.ID = (SELECT MAX(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
            LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
            WHERE (s.Page != 'collages' OR co.Deleted = '0')
                AND s.UserID = ?
            ", $user->id()
        );
    }
}
