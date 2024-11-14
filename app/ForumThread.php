<?php

namespace Gazelle;

class ForumThread extends BaseObject {
    final public const tableName        = 'forums_topics';
    final public const CACHE_KEY        = 'fthreadv2_%d';
    final public const CACHE_CATALOG    = 'fthread_cat_%d_%d';
    final protected const ID_THREAD_KEY = 'zz_ft_%d';

    // We need to remember to which forum the thread belongs in order
    // to adjust the forum after the thread is removed.
    protected int $forumId;

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        self::$cache->delete_value("edit_forums_{$this->id}");
        (new Manager\Forum())->flushToc();
        unset($this->info);
        return $this;
    }

    public function flushCatalogue(): void {
        self::$cache->delete_multi(
            array_map(
                fn ($n) => sprintf(self::CACHE_CATALOG, $this->id, $n),
                range(0, $this->lastCatalogue())
            )
        );
    }

    public function flushPostCatalogue(ForumPost $post): void {
        self::$cache->delete_multi(
            array_map(
                fn ($n) => sprintf(self::CACHE_CATALOG, $this->id, $n),
                range($post->threadCatalogue(), $this->lastCatalogue())
            )
        );
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title()));
    }

    public function location(): string {
        return "forums.php?action=viewthread&threadid={$this->id}";
    }

    /**
     * Get information about a thread
     * TODO: check if ever NumPosts != Posts
     */
    public function info(): array {
        if (isset($this->info)) {
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
                    max(fp.id)          AS last_post_id,
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

    /**
     * The body of the initial post in the thread
     */
    public function body(): string {
        $slice = $this->slice(1, 1);
        return count($slice) ? $slice[0]['Body'] : '';
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function forumId(): int {
        // this will survive a flush()
        return $this->forumId ??= $this->info()['forum_id'];
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
        return (bool)$this->info()['is_pinned'];
    }

    public function lastAuthorId(): int {
        return $this->info()['last_post_author_id'];
    }

    public function lastAuthor(): User {
        return new User($this->lastAuthorId());
    }

    public function lastPostId(): int {
        return $this->info()['last_post_id'];
    }

    public function lastPostTime(): string {
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

    public function lastCatalogue(): int {
        return (int)floor($this->postTotal() / THREAD_CATALOGUE);
    }

    public function catalogueEntry(int $page, int $perPage): int {
        return (int)floor($perPage * ($page - 1) / THREAD_CATALOGUE);
    }

    public function slice(int $page, int $perPage): array {
        // Cache catalogue from which the page is selected, allows block caches and future ability to specify posts per page
        $catId = $this->catalogueEntry(page: $page, perPage: $perPage);
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
            $catalogue = self::$db->to_array(false, MYSQLI_ASSOC, false);
            if (!$this->isLocked() || $this->isPinned()) {
                self::$cache->cache_value($key, $catalogue, 0);
            }
        }
        return array_slice($catalogue, ($perPage * ($page - 1)) % THREAD_CATALOGUE, $perPage, true);
    }

    public function addPost(User $user, string $body): ForumPost {
        $post = (new Manager\ForumPost())->create($this, $user, $body);
        $this->info();
        $this->info['post_total_summary']++;
        $this->info['last_post_id']        = $post->id();
        $this->info['last_post_author_id'] = $post->userId();

        $this->updateThread($post, 1);
        $user->stats()->increment('forum_post_total');
        return $post;
    }

    public function mergePost(ForumPost $post, User $user, string $body): int {
        $oldBody = $post->body();

        // Edit the post
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = CONCAT(Body, '\n\n', ?),
                EditedTime = now()
            WHERE ID = ?
            ", $user->id(), $body, $post->id()
        );
        $affected = self::$db->affected_rows();

        // Store edit history
        self::$db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page)
            VALUES (?,        ?,      ?,   'forums')
            ", $user->id(), $post->id(), $oldBody
        );
        self::$db->commit();

        $post->flush();
        $this->updateThread($post, 0);
        return $affected;
    }

    public function editThread(Forum $forum, bool $pinned, int $rank, bool $locked, string $title): int {
        self::$db->prepared_query("
            UPDATE forums_topics SET
                ForumID  = ?,
                IsSticky = ?,
                Ranking  = ?,
                IsLocked = ?,
                Title    = ?
            WHERE ID = ?
            ", $forum->id(), $pinned ? '1' : '0', $rank, $locked ? '1' : '0', trim($title),
            $this->id
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            if ($locked && $this->hasPoll()) {
                $this->poll()->close()->modify();
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
            $this->flushCatalogue();
            $this->flush();
            if ($this->forumId != $forum->id()) {
                $forum->adjust();
                $this->forumId = $forum->id();
            }
        }
        return $affected;
    }

    public function remove(): int {
        $this->flushCatalogue();

        // LastPostID is a chicken and egg situation when removing a thread,
        // so foreign key constraints must be suspended temporarily.
        $db = new \Gazelle\DB();
        $db->relaxConstraints(true);
        self::$db->prepared_query("
            DELETE ft, fp, unq
            FROM forums_topics AS ft
            LEFT JOIN forums_posts AS fp ON (fp.TopicID = ft.ID)
            LEFT JOIN users_notify_quoted as unq ON (unq.PageID = ft.ID AND unq.Page = 'forums')
            WHERE TopicID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $db->relaxConstraints(false);
        $this->forum()->adjust();

        (new Manager\Subscription())->move('forums', $this->id, null);

        $previousPost = self::$db->rowAssoc("
            SELECT AuthorID AS user_id,
                ID AS post_id
            FROM forums_posts
            WHERE TopicID = ?
            ORDER BY ID DESC
            LIMIT 1
            ", $this->id
        );
        if ($previousPost) {
            // there will be no posts when the last thread is removed
            $this->updateRoot($previousPost['user_id'], $previousPost['post_id']);
        }
        $this->flush();
        self::$cache->delete_value(sprintf(self::ID_THREAD_KEY, $this->id));
        return $affected;
    }

    public function addThreadNote(?User $user, string $notes): int {
        self::$db->prepared_query("
            INSERT INTO forums_topic_notes
                   (TopicID, AuthorID, Body)
            VALUES (?,       ?,        ?)
            ", $this->id, (int)($user?->id()), $notes
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

    /**
     * When updating the summary columns in the forums_topics rows, a new post
     * will increase the number of replies whereas a merged post will leave the
     * value untouched. Hence we pass in an increment value from the calling
     * method to ensure the total remains correct.
     * Note: NumPosts should really have been named NumReplies.
     */
    protected function updateThread(ForumPost $post, int $increment): int {
        self::$db->prepared_query("
            UPDATE forums_topics SET
                LastPostID       = ?,
                LastPostAuthorID = ?,
                LastPostTime     = ?,
                NumPosts         = NumPosts + ?
            WHERE ID = ?
            ", $post->id(), $post->userId(), $post->created(), $increment, $this->id
        );
        $affected = self::$db->affected_rows();
        $this->updateRoot($post->userId(), $post->id());
        (new Manager\Forum())->flushToc();
        $this->forum()->flush();
        $this->flushPostCatalogue($post);
        $this->flush();
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
    public function threadCatalogue(int $perPage, int $page): array {
        $catId = $this->catalogueEntry(page: $page, perPage: $perPage);
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

    public function catchup(User $user, int $postId): int {
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics
                   (UserID, TopicID, PostID)
            VALUES (?,      ?,       ?)
            ON DUPLICATE KEY UPDATE
                PostID = ?
            ", $user->id(), $this->id, $postId, $postId
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
    public function userLastReadPost(User $user): int {
        return (int)self::$db->scalar("
            SELECT PostID FROM forums_last_read_topics WHERE UserID = ? AND TopicID = ?
            ", $user->id(), $this->id
        );
    }
}
