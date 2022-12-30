<?php

namespace Gazelle;

class ForumThread extends BaseObject {
    const CACHE_KEY     = 'fthread_%d';
    const CACHE_CATALOG = 'fthread_cat_%d_%d';

    protected array $info;

    public function flush(): ForumThread {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        self::$cache->delete_value("edit_forums_{$this->id}");
        (new Manager\Forum)->flushToc();
        $last = $this->lastPage();
        $this->flushCatalog($last, $last);
        return $this;
    }

    public function flushCatalog(int $begin, int $end) {
        self::$cache->deleteMulti(
            array_map(
                fn($c) => sprintf(self::CACHE_CATALOG, $this->id, (int)floor((POSTS_PER_PAGE * $c) / THREAD_CATALOGUE)),
                range($begin, $end)
            )
        );
    }

    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title())); }
    public function location(): string { return "forums.php?action=viewthread&threadid={$this->id}"; }
    public function tableName(): string { return 'forums_topics'; }

    /**
     * Get information about a thread
     * TODO: check if ever NumPosts != Posts
     */
    public function info(): array {
        if (!empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT t.Title          AS title,
                    t.ForumID           AS forum_id,
                    t.AuthorID          AS author_id,
                    t.CreatedTime       AS created,
                    t.LastPostAuthorID  AS last_post_author_id,
                    t.StickyPostID      AS pinned_post_id,
                    t.Ranking           AS pinned_ranking,
                    t.NumPosts          AS post_total_summary,
                    t.IsLocked = '1'    AS is_locked,
                    t.IsSticky = '1'    AS is_pinned,
                    isnull(p.TopicID)   AS no_poll,
                    count(fp.id)        AS post_total,
                    max(fp.AddedTime)   AS last_post_time
                FROM forums_topics AS t
                INNER JOIN forums_posts AS fp ON (fp.TopicID = t.ID)
                LEFT JOIN forums_polls AS p ON (p.TopicID = t.ID)
                WHERE t.ID = ?
                GROUP BY t.ID
                ", $this->id
            );
            if ($info) {
                if ($info['pinned_post_id']) {
                    $info['post_total']--;
                }
                $info['pinned_post'] = self::$db->rowAssoc("
                    SELECT p.ID,
                        p.AuthorID,
                        p.AddedTime,
                        p.Body,
                        p.EditedUserID,
                        p.EditedTime
                    FROM forums_posts AS p
                    WHERE p.TopicID = ?
                        AND p.ID = ?
                    ", $this->id, $info['pinned_post_id']
                );
                self::$cache->cache_value($key, $info, 86400);
            }
        }
        $this->info = $info;
        return $this->info;
    }

    public function authorId(): int {
        return $this->info()['author_id'];
    }

    public function author(): User {
        return new User($this->authorId());
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function forumId(): int {
        return $this->info()['forum_id'];
    }

    public function forum(): Forum {
        return new Forum($this->forumId());
    }

    public function hasPoll(): bool {
        return !$this->info()['no_poll'];
    }

    public function poll(): ForumPoll {
        return new ForumPoll($this->id);
    }

    public function isLocked(): bool {
        return (bool)$this->info()['is_locked'];
    }

    public function isPinned(): bool {
        return (bool)$this->info()['is_locked'];
    }

    public function lastAuthorId(): int {
        return $this->info()['last_post_author_id'];
    }

    public function lastAuthor(): User {
        return new User($this->lastAuthorId());
    }

    public function lastPostTime(): int {
        return $this->info()['last_post_time'];
    }

    public function pinnedPostId(): ?int {
        return $this->info()['pinned_post_id'];
    }

    public function pinnedPostInfo(): array {
        return $this->info()['pinned_post'];
    }

    public function pinnedRanking(): int {
        return $this->info()['pinned_ranking'];
    }

    public function postTotal(): int {
        return $this->info()['post_total'];
    }

    public function postTotalSummary(): int {
        return $this->info()['post_total_summary'];
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function lastPage(): int {
        return (int)floor($this->postTotal() / POSTS_PER_PAGE);
    }

    public function lastCatalog(): int {
        return (int)floor($this->postTotal() / THREAD_CATALOGUE);
    }

    public function catalogEntry(int $page, int $perPage): int {
        return (int)floor($perPage * ($page - 1) / THREAD_CATALOGUE);
    }

    public function slice(int $page, int $perPage): array {
        // Cache catalogue from which the page is selected, allows block caches and future ability to specify posts per page
        $catId = $this->catalogEntry(page: $page, perPage: $perPage);
        $key = sprintf(self::CACHE_CATALOG, $this->id, $catId);
        $catalogue = self::$cache->get_value($key);
        if ($catalogue === false) {
            self::$db->prepared_query("
                SELECT p.ID,
                    p.AuthorID,
                    p.AddedTime,
                    p.Body,
                    p.EditedUserID,
                    p.EditedTime
                FROM forums_posts AS p
                WHERE p.TopicID = ?
                    AND p.ID != ?
                LIMIT ? OFFSET ?
                ", $this->id, $this->pinnedPostId(), THREAD_CATALOGUE, $catId * THREAD_CATALOGUE
            );
            $catalogue = self::$db->to_array(false, MYSQLI_ASSOC);
            if (!$this->isLocked() || $this->isPinned()) {
                self::$cache->cache_value($key, $catalogue, 0);
            }
        }
        return array_slice($catalogue, ($perPage * ($page - 1)) % THREAD_CATALOGUE, $perPage, true);
    }

    public function addPost(int $userId, string $body): int {
        $post = (new Manager\ForumPost)->create($this->id, $userId, $body);
        $postId = $post->id();
        $this->info();
        $this->info['post_total_summary']++;
        $this->info['last_post_id']        = $postId;
        $this->info['last_post_author_id'] = $userId;

        $this->updateThread($userId, $postId);
        (new Stats\User($userId))->increment('forum_post_total');
        return $postId;
    }

    public function mergePost(int $userId, string $body): int {
        [$postId, $oldBody] = self::$db->row("
            SELECT ID, Body
            FROM forums_posts
            WHERE TopicID = ?
                AND AuthorID = ?
            ORDER BY ID DESC LIMIT 1
            ", $this->id, $userId
        );

        // Edit the post
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = CONCAT(Body, '\n\n', ?),
                EditedTime = now()
            WHERE ID = ?
            ", $userId, $body, $postId
        );

        // Store edit history
        self::$db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page)
            VALUES (?,        ?,      ?,   'forums')
            ", $userId, $postId, $oldBody
        );
        self::$db->commit();

        $this->updateThread($userId, $postId);
        return $postId;
    }

    public function editThread(int $forumId, bool $pinned, int $rank, bool $locked, string $title): int {
        $oldForumId = $this->forumId();
        self::$db->prepared_query("
            UPDATE forums_topics SET
                ForumID  = ?,
                IsSticky = ?,
                Ranking  = ?,
                IsLocked = ?,
                Title    = ?
            WHERE ID = ?
            ", $forumId, $pinned ? '1' : '0', $rank, $locked ? '1' : '0', trim($title),
            $this->id
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            if ($locked && $this->hasPoll()) {
                $this->poll()->close();
            }
            if ($forumId != $oldForumId) {
                (new Forum($forumId))->adjust();
            }
            $this->updateRoot(
                ...self::$db->row("
                    SELECT AuthorID, ID
                    FROM forums_posts
                    WHERE TopicID = ?
                    ORDER BY ID DESC
                    LIMIT 1
                    ", $this->id
                )
            );
            $this->forum()->adjust();
            $this->flushCatalog(0, $this->lastPage());
            $this->flush();
        }
        return $affected;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE ft, fp, unq
            FROM forums_topics AS ft
            LEFT JOIN forums_posts AS fp ON (fp.TopicID = ft.ID)
            LEFT JOIN users_notify_quoted as unq ON (unq.PageID = ft.ID AND unq.Page = 'forums')
            WHERE TopicID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $this->forum()->adjust();

        (new Manager\Subscription)->move('forums', $this->id, null);

        $this->updateRoot(
            ...self::$db->row("
                SELECT AuthorID, ID
                FROM forums_posts
                WHERE TopicID = ?
                ORDER BY ID DESC
                LIMIT 1
                ", $this->id
            )
        );
        $this->flush();
        return $affected;
    }

    public function addThreadNote(int $userId, string $notes): int {
        self::$db->prepared_query("
            INSERT INTO forums_topic_notes
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $this->id, $userId, $notes
        );
        return self::$db->inserted_id();
    }

    public function threadNotes(): array {
        self::$db->prepared_query("
            SELECT ID, AuthorID, AddedTime, Body
            FROM forums_topic_notes
            WHERE TopicID = ?
            ORDER BY ID ASC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function pinPost(int $userId, int $postId, bool $set): int {
        $this->addThreadNote($userId, "Post $postId " . ($set ? "pinned" : "unpinned"));
        self::$db->prepared_query("
            UPDATE forums_topics SET
                StickyPostID = ?
            WHERE ID = ?
            ", $set ? $postId : 0, $this->id
        );
        $this->flush();
        $this->flushCatalog(0, $this->lastPage());
        return self::$db->affected_rows();
    }

    protected function updateThread(int $userId, int $postId): int {
        self::$db->prepared_query("
            UPDATE forums_topics SET
                NumPosts         = NumPosts + 1,
                LastPostTime     = now(),
                LastPostID       = ?,
                LastPostAuthorID = ?
            WHERE ID = ?
            ", $postId, $userId, $this->id()
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->updateRoot($userId, $postId);
            (new \Gazelle\Manager\Forum)->flushToc();
            $this->forum()->flush();
            $this->flush();
        }
        return $affected;
    }

    /**
     * Update the topic following the creation of a thread.
     * (Most recent thread and sets last poster).
     */
    protected function updateRoot(int $userId, int $postId): int {
        self::$db->prepared_query("
            UPDATE forums f
            INNER JOIN (
                SELECT ft.ForumID,
                    COUNT(DISTINCT ft.ID) as numThreads,
                    COUNT(fp.ID) as numPosts
                FROM forums_topics ft
                INNER JOIN forums_posts fp ON (fp.TopicID = ft.ID)
                WHERE ft.ForumID = ?
            ) STATS ON (STATS.ForumID = f.ID) SET
                f.NumTopics        = STATS.numThreads,
                f.NumPosts         = STATS.numPosts,
                f.LastPostID       = ?,
                f.LastPostAuthorID = ?,
                f.LastPostTopicID  = ?,
                f.LastPostTime     = now()
            WHERE f.ID = ?
            ", $this->forumId(), $postId,  $userId, $this->id, $this->forumId()
        );
        return self::$db->affected_rows();
    }

    /**
     * Get a catalogue of thread posts
     *
     * @return array [post_id, author_id, added_time, body, editor_user_id, edited_time]
     */
    public function threadCatalog(int $perPage, int $page): array {
        $catId = $this->catalogEntry(page: $page, perPage: $perPage);
        $key = sprintf(self::CACHE_CATALOG, $this->id, $catId);
        $catalogue = self::$cache->get_value($key);
        if ($catalogue === false) {
            self::$db->prepared_query("
                SELECT p.ID         AS post_id,
                    p.AuthorID      AS author_id,
                    p.AddedTime     AS added,
                    p.Body          AS body,
                    p.EditedUserID  AS edit_user_id
                FROM forums_posts AS p
                WHERE p.TopicID = ?
                    AND p.ID != ?
                LIMIT ? OFFSET ?
                ", $this->id, $this->pinnedPostId(), THREAD_CATALOGUE, $catId * THREAD_CATALOGUE
            );
            $catalogue = self::$db->to_array(false, MYSQLI_ASSOC, false);
            if (!$this->isLocked() || $this->isPinned()) {
                self::$cache->cache_value($key, $catalogue, 0);
            }
        }
        return $catalogue;
    }

    public function catchup(int $userId, int $postId): int {
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics
                   (UserID, TopicID, PostID)
            VALUES (?,      ?,       ?)
            ON DUPLICATE KEY UPDATE
                PostID = ?
            ", $userId, $this->id, $postId, $postId
        );
        return self::$db->affected_rows();
    }

    public function clearUserLastRead(): int {
        self::$db->prepared_query("
            DELETE FROM forums_last_read_topics
            WHERE TopicID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Return the last (highest previously read) post ID for a user in this thread.
     */
    public function userLastReadPost(int $userId): int {
        return (int)self::$db->scalar("
            SELECT PostID FROM forums_last_read_topics WHERE UserID = ? AND TopicID = ?
            ", $userId, $this->id
        );
    }

    /**
     * The number of posts up to the given post in the thread
     * If $hasSticky is true, count will be one less.
     */
    public function lesserPostTotal(int $postId): int {
        return self::$db->scalar("
            SELECT count(*) FROM forums_posts WHERE TopicID = ? AND ID <= ?
            ", $this->id, $postId
        ) - (int)$this->isPinned();
    }
}
