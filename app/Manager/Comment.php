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
        return new $className($pageId, $postId);
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
        return new $className($pageId, $postId);
    }
}
