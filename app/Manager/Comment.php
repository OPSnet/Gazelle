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

        // Clear cache
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $CommPages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        for ($i = 0; $i <= $last; ++$i) {
            $this->cache->delete_value($page . '_comments_' . $pageId . '_catalogue_' . $i);
        }
        $this->cache->delete_value($page . '_comments_' . $pageId);
        $this->db->set_query_id($qid);

        return true;
    }

    public function loadEdits(string $page, int $postId): array {
        $key = "{$page}_edits_{$postId}";
        if (($edits = $this->cache->get_value($key)) === false) {
            $this->db->prepared_query("
                SELECT EditUser, EditTime, Body
                FROM comments_edits
                WHERE Page = ?
                    AND PostID = ?
                ORDER BY EditTime DESC
                ", $page, $postId
            );
            $edits = $this->db->to_array();
            $this->cache->cache_value($key, $edits, 0);
        }
        return $edits;
    }
}
