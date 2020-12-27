<?php

namespace Gazelle\Comment;

abstract class AbstractComment extends \Gazelle\BaseObject {
    protected $pageId;
    protected $userId;

    public function tableName(): string {
        return 'comments';
    }

    public function __construct(int $pageId, int $postId) {
        parent::__construct($postId);
        $this->pageId = $pageId;
    }

    abstract public function page(): string;
    abstract public function pageUrl(): string;

    public function url(): string {
        return $this->pageUrl()
            . "{$this->pageId}&postid={$this->id}#post{$this->id}";
    }

    public function flush() {
        // No-op: There is no such thing as an individual comment cache
    }

    public function userId(): int {
        if (is_null($this->userId)) {
            $this->userId = $this->db->scalar("
                SELECT AuthorID FROM comments WHERE ID = ?
                ", $this->id
            );
        }
        return $this->userId;
    }

    public function isAuthor(int $userId): bool {
        return $this->userId() === $userId;
    }

    public function setEditedUserID(int $userId) {
        $this->setUpdate('EditedUserID', $userId);
        return $this;
    }

    public function setBody(string $body) {
        $this->setUpdate('Body', trim($body));
        return $this;
    }

    /**
     * Modify a comment (saving the previous revision)
     */
    public function modify(): bool {
        $body = $this->db->scalar("
            SELECT Body FROM comments WHERE ID = ?
            ", $this->id
        );
        if (is_null($body)) {
            return false;
        }

        $this->db->begin_transaction();

        $success = parent::modify();
        if (!$success) {
            $this->db->rollback();
            return false;
        }

        $page = $this->page();
        $this->db->prepared_query("
            INSERT INTO comments_edits
                   (Page, PostID, Body, EditUser)
            VALUES (?,    ?,      ?,    ?)
            ", $page, $this->id(), $body, $this->field('EditedUserID')
        );
        $this->db->commit();

        $commentPage = $this->db->scalar("
            SELECT ceil(count(*) / ?) AS Page
            FROM comments
            WHERE Page = ?
                AND PageID = ?
                AND ID <= ?
            ", TORRENT_COMMENTS_PER_PAGE, $page, $this->pageId, $this->id
        );

        // Update the cache
        $this->cache->delete_value("{$page}_comments_{$this->pageId}_catalogue_"
            . floor((($commentPage - 1) * TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE)
        );

        if ($page == 'collages') {
            // On collages, we also need to clear the collage key (collage_$CollageID), because it has the comments in it... (why??)
            $this->cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $this->pageId));
        }

        return true;
    }

    public function remove(): bool {
        $page = $this->page();
        [$commentPages, $commentPage] = $this->db->row("
            SELECT
                ceil(count(*) / ?) AS Pages,
                ceil(sum(if(ID <= ?, 1, 0)) / ?) AS Page
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $this->id, TORRENT_COMMENTS_PER_PAGE, $page, $this->pageId
        );
        if (is_null($commentPages)) {
            return false;
        }

        $this->db->begin_transaction();
        $this->db->prepared_query("
            DELETE FROM comments WHERE ID = ?
            ", $this->id
        );
        $this->db->prepared_query("
            DELETE FROM comments_edits WHERE Page = ? AND PostID = ?
            ", $page, $this->id
        );
        $this->db->prepared_query("
            DELETE FROM users_notify_quoted WHERE Page = ? AND PostID = ?
            ", $page, $this->id
        );
        $this->db->commit();

        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->flush($page, $this->pageId);
        $subscription->flushQuotes($page, $this->pageId);

        //We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
        $current = floor((TORRENT_COMMENTS_PER_PAGE * $commentPage - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $commentPages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        $keyStem = "{$page}_comments_{$this->pageId}_catalogue_";
        for ($i = $current; $i <= $last; ++$i) {
            $this->cache->delete_value($keyStem . $i);
        }
        $this->cache->delete_value("{$page}_comments_" . $this->pageId);

        if ($page === 'collages') {
            // On collages, we also need to clear the collage key (collage_$CollageID), because it has the comments in it... (why??)
            $this->cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $this->id));
        }
        return true;
    }
}
