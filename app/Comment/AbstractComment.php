<?php

namespace Gazelle\Comment;

abstract class AbstractComment extends \Gazelle\BaseObject {
    final public const tableName     = 'comments';
    final protected const PAGE_TOTAL = '%s_comments_%d';

    protected int   $userId;
    protected int   $lastRead = 0;
    protected int   $total;   // number of comments
    protected array $thread = []; // the page of comments

    abstract public function page(): string;

    abstract public function pageUrl(): string;

    public function flush(): static {
        // No-op: There is no such thing as an individual comment cache
        return $this;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), "Comment #" . $this->id);
    }

    public function location(): string {
        return $this->pageUrl() . "{$this->pageId}&postid={$this->id}#post{$this->id}";
    }

    public function __construct(
        protected int $pageId,
        protected int $pageNum,
        protected int $id,
    ) {
        parent::__construct($id);
    }

    public function body(): string {
        return (string)self::$db->scalar("
            SELECT Body FROM comments WHERE Page = ?  AND ID = ?
            ", $this->page(), $this->id
        );
    }

    public function isAuthor(\Gazelle\User $user): bool {
        return $this->userId() === $user->id();
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

    public function threadList(\Gazelle\Manager\User $manager): array {
        $cache = [];
        $list  = [];
        foreach ($this->thread() as $post) {
            [$postId, $userId, $created, $body, $editedUserID, $editedTime, $editedUsername] = array_values($post);
            if (!isset($cache[$userId])) {
                $cache[$userId] = $manager->findById($userId);
            }
            $author = $cache[$userId];
            $list[] = [
                'postId'         => $postId,
                'authorId'       => $userId,
                'name'           => $author->username(),
                'donor'          => (new \Gazelle\User\Donor($author))->isDonor(),
                'warned'         => $author->isWarned(),
                'enabled'        => $author->isEnabled(),
                'class'          => $manager->userclassName($author->primaryClass()),
                'addedTime'      => $created,
                'avatar'         => $author->avatar(),
                'bbBody'         => $body,
                'comment'        => \Text::full_format($body),
                'editedUserId'   => $editedUserID,
                'editedUsername' => $editedUsername,
                'editedTime'     => $editedTime
            ];
        }
        return $list;
    }

    public function total(): int {
        return $this->total;
    }

    public function userId(): int {
        if (!isset($this->userId)) {
            $this->userId = (int)self::$db->scalar("
                SELECT AuthorID FROM comments WHERE ID = ?
                ", $this->id
            );
        }
        return $this->userId;
    }

    /**
     * Load a page of comments
     */
    public function load(): static {
        $page = $this->page();
        $pageId = $this->pageId;

        // Get the total number of comments
        $key = sprintf(self::PAGE_TOTAL, $page, $pageId);
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $total = (int)self::$db->scalar("
                SELECT count(*) FROM comments WHERE Page = ? AND PageID = ?
                ", $page, $pageId
            );
            self::$cache->cache_value($key, $total, 0);
        }
        $this->total = $total;

        if (!$this->pageNum) {
            // default to final page, or page where specified post is found
            if (!$this->id) {
                $this->pageNum = $this->total ? (int)ceil($this->total / TORRENT_COMMENTS_PER_PAGE) : 1;
            } else {
                $this->pageNum = (int)self::$db->scalar("
                    SELECT ceil(count(*) / ?)
                    FROM comments
                    WHERE Page = ?
                        AND PageID = ?
                        AND ID <= ?
                    ", TORRENT_COMMENTS_PER_PAGE, $page, $pageId, $this->id
                );
            }
        }

        // Cache catalogue from which the page is selected
        $CatalogueID = (int)floor(TORRENT_COMMENTS_PER_PAGE * ($this->pageNum - 1) / THREAD_CATALOGUE);
        $catKey = sprintf(\Gazelle\Manager\Comment::CATALOG, $page, $pageId, $CatalogueID);
        $Catalogue = self::$cache->get_value($catKey);
        if ($Catalogue === false) {
            self::$db->prepared_query("
                SELECT c.ID,
                    c.AuthorID,
                    c.AddedTime,
                    c.Body,
                    c.EditedUserID,
                    c.EditedTime,
                    u.Username,
                    a.Username AS author_name
                FROM comments AS c
                LEFT JOIN users_main AS a ON (a.ID = c.AuthorID)
                LEFT JOIN users_main AS u ON (u.ID = c.EditedUserID)
                WHERE c.Page = ? AND c.PageID = ?
                ORDER BY c.ID
                LIMIT ? OFFSET ?
                ", $page, $pageId, THREAD_CATALOGUE, THREAD_CATALOGUE * $CatalogueID
            );
            $Catalogue = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($catKey, $Catalogue, 0);
        }

        //This is a hybrid to reduce the catalogue down to the page elements: We use the page limit % catalogue
        $this->thread = array_slice($Catalogue,
            (TORRENT_COMMENTS_PER_PAGE * ($this->pageNum - 1)) % THREAD_CATALOGUE, TORRENT_COMMENTS_PER_PAGE, true
        );
        return $this;
    }

    public function handleSubscription(\Gazelle\User $user): int {
        if (empty($this->thread)) {
            return 0;
        }
        $lastPost = end($this->thread)['ID'];
        $page = $this->page();
        $pageId = $this->pageId;
        $userId = $user->id();

        // quote notifications
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE users_notify_quoted SET
                UnRead = false
            WHERE Page = ?
                AND PageID = ?
                AND PostID BETWEEN ? AND ?
                AND UserID = ?
            ", $page, $pageId, current($this->thread)['ID'], $lastPost, $userId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            (new \Gazelle\User\Quote($user))->flush();
        }

        // last read
        $this->lastRead = (int)self::$db->scalar("
            SELECT PostID
            FROM users_comments_last_read
            WHERE Page = ?
                AND PageID = ?
                AND UserID = ?
            ", $page, $pageId, $userId
        );
        if ($this->lastRead < $lastPost) {
            self::$db->prepared_query("
                INSERT INTO users_comments_last_read
                       (UserID, Page, PageID, PostID)
                VALUES (?,      ?,    ?,      ?)
                ON DUPLICATE KEY UPDATE
                    PostID = ?
                ", $userId, $page, $pageId, $lastPost, $lastPost
            );
            self::$cache->delete_value("subscriptions_user_new_$userId");
        }
        self::$db->commit();
        return $affected;
    }

    /**
     * Modify a comment (saving the previous revision)
     */
    public function modify(): bool {
        $body = self::$db->scalar("
            SELECT Body FROM comments WHERE ID = ?
            ", $this->id
        );
        if (is_null($body)) {
            return false;
        }

        self::$db->begin_transaction();
        $page = $this->page();
        self::$db->prepared_query("
            INSERT INTO comments_edits
                   (Page, PostID, Body, EditUser)
            VALUES (?,    ?,      ?,    ?)
            ", $page, $this->id, $body, $this->field('EditedUserID')
        );
        $success = parent::modify();
        if (!$success) {
            self::$db->rollback();
            return false;
        }
        self::$db->commit();

        $commentPage = (int)self::$db->scalar("
            SELECT ceil(count(*) / ?) AS Page
            FROM comments
            WHERE Page = ?
                AND PageID = ?
                AND ID <= ?
            ", TORRENT_COMMENTS_PER_PAGE, $page, $this->pageId, $this->id
        );

        // Update the cache
        self::$cache->delete_multi([
            "edit_{$page}_" . $this->id,
            "{$page}_comments_" . $this->pageId,
            sprintf(\Gazelle\Manager\Comment::CATALOG, $page, $this->pageId,
                (int)floor((($commentPage - 1) * TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE)
            ),
        ]);

        if ($page == 'collages') {
            // On collages, we also need to clear the collage key (collage_$CollageID), because it has the comments in it... (why??)
            self::$cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $this->pageId));
        }

        return true;
    }

    public function remove(): bool {
        $page = $this->page();
        [$commentPages, $commentPage] = self::$db->row("
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

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM comments WHERE ID = ?
            ", $this->id
        );
        self::$db->prepared_query("
            DELETE FROM comments_edits WHERE Page = ? AND PostID = ?
            ", $page, $this->id
        );
        self::$db->prepared_query("
            DELETE FROM users_notify_quoted WHERE Page = ? AND PostID = ?
            ", $page, $this->id
        );
        self::$db->commit();

        (new \Gazelle\Manager\Subscription())->flushPage($page, $this->pageId);

        self::$cache->delete_multi([
            "edit_{$page}_" . $this->id,
            "{$page}_comments_" . $this->pageId,
        ]);

        // We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
        $current = floor((TORRENT_COMMENTS_PER_PAGE * $commentPage - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $commentPages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        for ($i = $current; $i <= $last; ++$i) {
            self::$cache->delete_value(sprintf(\Gazelle\Manager\Comment::CATALOG, $page, $this->pageId, $i));
        }

        if ($page === 'collages') {
            // On collages, we also need to clear the collage key (collage_$CollageID), because it has the comments in it... (why??)
            self::$cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $this->id));
        }
        return true;
    }
}
