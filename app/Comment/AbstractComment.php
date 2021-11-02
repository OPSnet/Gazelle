<?php

namespace Gazelle\Comment;

abstract class AbstractComment extends \Gazelle\BaseObject {
    protected $pageId;
    protected $userId;
    protected $lastRead = 0;
    protected $pageNum;     // which page to view
    protected $total = 0;   // number of comments
    protected $thread = []; // the page of comments
    protected $viewer;

    protected const PAGE_TOTAL = '%s_comments_%d';
    protected const CATALOG = '%s_comments_%d_catalogue_%d';

    abstract public function page(): string;
    abstract public function pageUrl(): string;

    public function tableName(): string {
        return 'comments';
    }

    public function url(): string {
        return $this->pageUrl() . "{$this->pageId}&postid={$this->id}#post{$this->id}";
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), "Post #" . $this->id);
    }

    public function __construct(int $pageId) {
        parent::__construct(0);
        $this->pageId = $pageId;
    }

    public function lastRead(): int {
        return $this->lastRead;
    }

    public function pageNum(): int {
        return $this->pageNum;
    }

    public function thread(): array {
        return $this->thread;
    }

    public function total(): int {
        return $this->total;
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

    public function setPageNum(int $pageNum) {
        $this->pageNum = $pageNum;
        return $this;
    }

    public function setPostId(int $id) {
        $this->id = $id;
        return $this;
    }

    public function setViewer(\Gazelle\User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

    public function setBody(string $body) {
        $this->setUpdate('Body', trim($body));
        return $this;
    }

    public function body(): string {
        return $this->db->scalar("
            SELECT Body FROM comments WHERE Page = ?  AND ID = ?
            ", $this->page(), $this->id
        );
    }

    /**
     * Load a page of comments
     */
    public function load() {
        $page = $this->page();
        $pageId = $this->pageId;

        // Get the total number of comments
        $key = sprintf(self::PAGE_TOTAL, $page, $pageId);
        $this->total = $this->cache->get_value($key);
        if ($this->total === false) {
            $this->total = $this->db->scalar("
                SELECT count(*) FROM comments WHERE Page = ? AND PageID = ?
                ", $page, $pageId
            );
            $this->cache->cache_value($key, $this->total, 0);
        }

        if (is_null($this->pageNum)) {
            // default to final page, or page where specified post is found
            if (!$this->id) {
                $this->pageNum = (int)ceil($this->total / TORRENT_COMMENTS_PER_PAGE);
            } else {
                $prior = $this->db->scalar("
                    SELECT count(*)
                    FROM comments
                    WHERE Page = ?
                        AND PageID = ?
                        AND ID <= ?
                    ", $page, $pageId, $this->id
                );
                $this->pageNum = (int)ceil($prior / TORRENT_COMMENTS_PER_PAGE);
            }
        }

        // Cache catalogue from which the page is selected
        $CatalogueID = (int)floor(TORRENT_COMMENTS_PER_PAGE * ($this->pageNum - 1) / THREAD_CATALOGUE);
        $catKey = sprintf(self::CATALOG, $page, $pageId, $CatalogueID);
        if (($Catalogue = $this->cache->get_value($catKey)) === false) {
            $this->db->prepared_query("
                SELECT c.ID,
                    c.AuthorID,
                    c.AddedTime,
                    c.Body,
                    c.EditedUserID,
                    c.EditedTime,
                    u.Username
                FROM comments AS c
                LEFT JOIN users_main AS u ON (u.ID = c.EditedUserID)
                WHERE c.Page = ? AND c.PageID = ?
                ORDER BY c.ID
                LIMIT ? OFFSET ?
                ", $page, $pageId, THREAD_CATALOGUE, THREAD_CATALOGUE * $CatalogueID
            );
            $Catalogue = $this->db->to_array(false, MYSQLI_ASSOC);
            $this->cache->cache_value($catKey, $Catalogue, 0);
        }

        //This is a hybrid to reduce the catalogue down to the page elements: We use the page limit % catalogue
        $this->thread = array_slice($Catalogue,
            (TORRENT_COMMENTS_PER_PAGE * ($this->pageNum - 1)) % THREAD_CATALOGUE, TORRENT_COMMENTS_PER_PAGE, true
        );
        return $this;
    }

    public function handleSubscription(\Gazelle\User $user) {
        if (empty($this->thread)) {
            return;
        }
        $lastPost = end($this->thread)['ID'];
        $page = $this->page();
        $pageId = $this->pageId;
        $userId = $user->id();

        // quote notifications
        $this->db->begin_transaction();
        $this->db->prepared_query("
            UPDATE users_notify_quoted SET
                UnRead = false
            WHERE Page = ?
                AND PageID = ?
                AND PostID BETWEEN ? AND ?
                AND UserID = ?
            ", $page, $pageId, current($this->thread)['ID'], $lastPost, $userId
        );
        if ($this->db->affected_rows()) {
            $this->cache->delete_value("user_quote_unread_$userId");
        }

        // last read
        $this->lastRead = $this->db->scalar("
            SELECT PostID
            FROM users_comments_last_read
            WHERE Page = ?
                AND PageID = ?
                AND UserID = ?
            ", $page, $pageId, $userId
        ) ?? 0;
        if ($this->lastRead < $lastPost) {
            $this->db->prepared_query("
                INSERT INTO users_comments_last_read
                       (UserID, Page, PageID, PostID)
                VALUES (?,      ?,    ?,      ?)
                ON DUPLICATE KEY UPDATE
                    PostID = ?
                ", $userId, $page, $pageId, $lastPost, $lastPost
            );
            $this->cache->delete_value("subscriptions_user_new_$userId");
        }
        $this->db->commit();
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
            ", $page, $this->id, $body, $this->field('EditedUserID')
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
        $this->cache->deleteMulti([
            "edit_{$page}_" . $this->id,
            "{$page}_comments_" . $this->pageId,
            "{$page}_comments_{$this->pageId}_catalogue_"
                . (int)floor((($commentPage - 1) * TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE),
        ]);

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

        (new \Gazelle\Manager\Subscription)->flush($page, $this->pageId);

        $this->cache->deleteMulti([
            "edit_{$page}_" . $this->id,
            "{$page}_comments_" . $this->pageId,
        ]);

        //We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
        $current = floor((TORRENT_COMMENTS_PER_PAGE * $commentPage - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $commentPages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        $keyStem = "{$page}_comments_{$this->pageId}_catalogue_";
        for ($i = $current; $i <= $last; ++$i) {
            $this->cache->delete_value($keyStem . $i);
        }

        if ($page === 'collages') {
            // On collages, we also need to clear the collage key (collage_$CollageID), because it has the comments in it... (why??)
            $this->cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $this->id));
        }
        return true;
    }
}
