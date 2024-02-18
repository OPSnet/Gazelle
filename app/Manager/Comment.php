<?php

namespace Gazelle\Manager;

class Comment extends \Gazelle\BaseManager {
    final public const CATALOG = '%s_comments_%d_cat_%d';

    protected function className(string $page): string {
        return match ($page) {
            'artist'   => \Gazelle\Comment\Artist::class,
            'collages' => \Gazelle\Comment\Collage::class,
            'requests' => \Gazelle\Comment\Request::class,
            'torrents' => \Gazelle\Comment\Torrent::class,
            default    => error("no comments for " . display_str($page)),
        };
    }

    /**
     * Post a comment on an artist, request or torrent page.
     */
    public function create(
        \Gazelle\User $user,
        string $page,
        int $pageId,
        string $body,
    ): \Gazelle\Comment\Artist|\Gazelle\Comment\Collage|\Gazelle\Comment\Request|\Gazelle\Comment\Torrent {
        self::$db->prepared_query("
            INSERT INTO comments
                   (Page, PageID, AuthorID, Body)
            VALUES (?,    ?,      ?,        ?)
            ", $page, $pageId, $user->id(), $body
        );
        $postId = self::$db->inserted_id();
        $catalogueId = (int)self::$db->scalar("
            SELECT floor((ceil(count(*) / ?) - 1) * ? / ?)
            FROM comments
            WHERE Page = ? AND PageID = ?
            ", TORRENT_COMMENTS_PER_PAGE, TORRENT_COMMENTS_PER_PAGE, THREAD_CATALOGUE, $page, $pageId
        );
        self::$cache->delete_multi([
            sprintf(self::CATALOG, $page, $pageId, $catalogueId),
            "{$page}_comments_{$pageId}"
        ]);
        if ($page == 'collages') {
            self::$cache->delete_value("{$page}_comments_recent_{$pageId}");
        }
        (new \Gazelle\User\Notification\Quote($user))
            ->create(new \Gazelle\Manager\User(), $body, $postId, $page, $pageId);
        (new Subscription())->flushPage($page, $pageId);

        $className = $this->className($page);
        return new $className($pageId, 0, $postId); /** @phpstan-ignore-line */
    }

    public function findById(
        int $postId,
    ): \Gazelle\Comment\Artist|\Gazelle\Comment\Collage|\Gazelle\Comment\Request|\Gazelle\Comment\Torrent|null {
        [$page, $pageId] = self::$db->row("
            SELECT Page, PageID FROM comments WHERE ID = ?
            ", $postId
        );
        if (is_null($page)) {
            return null;
        }
        $className = $this->className($page);
        return new $className($pageId, 0, $postId); /** @phpstan-ignore-line */
    }

    public function findBodyById(int $postId): ?string {
        $body = self::$db->scalar("
            SELECT Body FROM comments WHERE ID = ?
            ", $postId
        );
        return is_null($body) ? null : (string)$body;
    }

    public function merge(string $page, int $pageId, int $targetPageId): int {
        self::$db->prepared_query("
            UPDATE comments SET
                PageID = ?
            WHERE Page = ? AND PageID = ?
            ", $targetPageId, $page, $pageId
        );
        $affected = self::$db->affected_rows();
        $pageCount = (int)self::$db->scalar("
            SELECT ceil(count(*) / ?) AS Pages
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $page, $targetPageId
        );
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $pageCount - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);

        // quote notifications
        self::$db->prepared_query("
            UPDATE users_notify_quoted SET
                PageID = ?
            WHERE Page = ? AND PageID = ?
            ", $targetPageId, $page, $pageId
        );

        // comment subscriptions
        $subscription = new \Gazelle\Manager\Subscription();
        $subscription->move($page, $pageId, $targetPageId);

        for ($i = 0; $i <= $last; ++$i) {
            self::$cache->delete_value(sprintf(self::CATALOG, $page, $targetPageId, $i));
        }
        self::$cache->delete_value("{$page}_comments_{$targetPageId}");
        return $affected;
    }

    /**
     * Remove all comments on $page/$pageId (handle quote notifications and subscriptions as well)
     *
     * @return int number of comments that were removed
     */
    public function remove(string $page, int $pageId): int {
        $qid = self::$db->get_query_id();

        $pageCount = (int)self::$db->scalar("
            SELECT ceil(count(*) / ?) AS Pages
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $page, $pageId
        );
        if ($pageCount === 0) {
            return 0;
        }
        $last = floor((TORRENT_COMMENTS_PER_PAGE * $pageCount - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);

        self::$db->prepared_query("
            DELETE FROM comments WHERE Page = ? AND PageID = ?
            ", $page, $pageId
        );
        $affected = self::$db->affected_rows();

        // literally, move the comment thread to nowhere i.e. delete
        (new \Gazelle\Manager\Subscription())->move($page, $pageId, null);

        self::$db->prepared_query("
            DELETE FROM users_notify_quoted WHERE Page = ? AND PageID = ?
            ", $page, $pageId
        );
        self::$db->set_query_id($qid);

        // Clear cache
        for ($i = 0; $i <= $last; ++$i) {
            self::$cache->delete_value(sprintf(self::CATALOG, $page, $pageId, $i));
        }
        self::$cache->delete_value($page . '_comments_' . $pageId);

        return $affected;
    }

    public function loadEdits(string $page, int $postId): array {
        $key = "edit_{$page}_{$postId}";
        $edits = self::$cache->get_value($key);
        if ($edits === false) {
            self::$db->prepared_query("
                SELECT EditUser, EditTime, Body
                FROM comments_edits
                WHERE Page = ?
                    AND PostID = ?
                ORDER BY EditTime DESC
                ", $page, $postId
            );
            $edits = self::$db->to_array(false, MYSQLI_NUM, false);
            self::$cache->cache_value($key, $edits, 7200);
        }
        return $edits;
    }

    /**
     * Load recent collage comments. Used for displaying recent comments on collage pages.
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
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $qid = self::$db->get_query_id();
            self::$db->prepared_query("
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
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$db->set_query_id($qid);
            if (count($list)) {
                self::$cache->cache_value($key, $list, 7200);
            }
        }
        return $list;
    }
}
