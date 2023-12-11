<?php

namespace Gazelle;

class Forum extends BaseObject {
    use Pg;

    final const tableName         = 'forums';
    final const CACHE_TOC_FORUM   = 'forum_tocv2_%d';
    final const CACHE_FORUM       = 'forum_%d';
    final const CACHE_THREAD_INFO = 'thread_%d_info';
    final const CACHE_CATALOG     = 'thread_%d_catalogue_%d';

    public function location(): string {
        return 'forums.php?action=viewforum&forumid=' . $this->id;
    }

    public function link(): string {
        return sprintf('<a href="%s" class="tooltip" title="%s">%s</a>',
            $this->url(), display_str($this->name()), display_str(shortenString($this->name(), 75))
        );
    }

    public function flush(): static {
        $this->info = [];
        (new Manager\Forum)->flushToc();
        self::$cache->delete_multi([
            sprintf(self::CACHE_FORUM, $this->id),
            sprintf(self::CACHE_TOC_FORUM, $this->id),
        ]);
        return $this;
    }

    // TODO: rewrite to use BaseObject::modify()
    public function modifyForum(array $args): bool {
        self::$db->prepared_query("
            UPDATE forums SET
                Sort           = ?,
                CategoryID     = ?,
                Name           = ?,
                Description    = ?,
                MinClassRead   = ?,
                MinClassWrite  = ?,
                MinClassCreate = ?,
                AutoLock       = ?,
                AutoLockWeeks  = ?
            WHERE ID = ?
            ", (int)$args['sort'], (int)$args['categoryid'], trim($args['name']), trim($args['description']),
               (int)$args['minclassread'], (int)$args['minclasswrite'], (int)$args['minclasscreate'],
               isset($args['autolock']) ? '1' : '0', (int)$args['autolockweeks'],
               $this->id
        );
        $changed = self::$db->affected_rows();
        $this->flush();
        return $changed == 1;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM forums WHERE ID = ?
            ", $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    /**
     * Basic information about a forum
     *
     * @return array [forum_id, name, description, category_id,
     *      min_class_read, min_class_write, min_class_create, sequence, auto_lock, auto_lock_weeks]
     */
    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_FORUM, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT f.ID            AS forum_id,
                    f.Name             AS name,
                    f.Description      AS description,
                    f.CategoryID       AS category_id,
                    cat.Name           AS category_name,
                    f.MinClassRead     AS min_class_read,
                    f.MinClassWrite    AS min_class_write,
                    f.MinClassCreate   AS min_class_create,
                    f.NumTopics        AS num_threads,
                    f.NumPosts         AS num_posts,
                    f.LastPostID       AS last_post_id,
                    f.LastPostAuthorID AS last_author_id,
                    f.LastPostTopicID  AS last_thread_id,
                    ft.Title           AS last_thread,
                    f.LastPostTime     AS last_post_time,
                    f.Sort             AS sequence,
                    f.AutoLock         AS auto_lock,
                    f.AutoLockWeeks    AS auto_lock_weeks
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                LEFT JOIN forums_topics ft ON (ft.ID = f.LastPostTopicID)
                WHERE f.ID = ?
                ", $this->id
            );
            self::$cache->cache_value($key, $info, 86400);
        }
        $this->info = $info;
        return $this->info;
    }

    public function autoLock(): bool {
        return $this->info()['auto_lock'] == '1';
    }

    public function autoLockWeeks(): int {
        return $this->info()['auto_lock_weeks'];
    }

    public function categoryId(): int {
        return $this->info()['category_id'];
    }

    public function categoryName(): string {
        return $this->info()['category_name'];
    }

    public function description(): string {
        return $this->info()['description'];
    }

    public function hasRevealVotes(): bool {
        return in_array($this->id, FORUM_REVEAL_VOTE);
    }

    public function isLocked(): bool {
        return (bool)$this->info()['is_locked'];
    }

    public function lastPostId(): int {
        return $this->info()['last_post_id'];
    }

    public function lastAuthorId(): ?int {
        return $this->info()['last_author_id'];
    }

    public function lastThread(): ?string {
        return $this->info()['last_thread'];
    }

    public function lastThreadId(): int {
        return $this->info()['last_thread_id'];
    }

    public function lastPostTime(): int {
        return $this->info()['last_post_time'] ? strtotime($this->info()['last_post_time']) : 0;
    }

    public function minClassCreate(): int {
        return $this->info()['min_class_create'];
    }

    public function minClassRead(): int {
        return $this->info()['min_class_read'];
    }

    public function minClassWrite(): int {
        return $this->info()['min_class_write'];
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function numPosts(): int {
        return $this->info()['num_posts'];
    }

    public function numThreads(): int {
        return $this->info()['num_threads'];
    }

    public function sequence(): int {
        return $this->info()['sequence'];
    }

    /**
     * Adjust the number of topics and posts of a forum.
     * used when deleting a thread or moving a thread between forums.
     * Recalculates the last post details in case the move changes things.
     * NB: This uses a forumID passed in explicitly, because
     * moving threads requires two calls and it just makes things
     * a bit clearer.
     */
    public function adjust(): int {
        /* Recalculate the correct values from first principles.
         * This does not happen very often, and only a moderator
         * pays the cost. At least this way the numbers correct
         * themselves if ever they drift out of synch -- Spine
         */
        self::$db->prepared_query("
            UPDATE forums f
            LEFT JOIN (
                /* these are the values to update the row in the forums table */
                SELECT count(DISTINCT fp.ID) as NumPosts,
                    count(DISTINCT ft.ID) as NumTopics,
                    LAST.ID, LAST.TopicID, LAST.AuthorID, LAST.AddedTime,
                    ft.ForumID
                FROM forums_posts fp
                LEFT JOIN forums_topics ft ON (ft.ID = fp.TopicID)
                LEFT JOIN (
                    /* find the most recent post of any thread in the forum */
                    SELECT p.ID, p.TopicID, p.AuthorID, p.AddedTime, t.ForumID
                    FROM forums_posts p
                    INNER JOIN forums_topics t ON (t.ID = p.TopicID)
                    WHERE t.ForumID = ?
                    ORDER BY p.AddedTime DESC
                    LIMIT 1
                ) AS LAST USING (ForumID)
                WHERE ft.ForumID = ?
            ) POST ON (POST.ForumID = f.ID)
            SET
                f.NumTopics        = coalesce(POST.NumTopics, 0),
                f.NumPosts         = coalesce(POST.NumPosts,  0),
                f.LastPostTopicID  = coalesce(POST.TopicID,   0),
                f.LastPostID       = coalesce(POST.ID,        0),
                f.LastPostAuthorID = coalesce(POST.AuthorID,  0),
                f.LastPostTime     = POST.AddedTime
            WHERE f.ID = ?
            ", $this->id, $this->id, $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function threadCount(): int {
        $toc = $this->tableOfContentsForum();
        return $toc ? current($toc)['threadCount'] : 0;
    }

    /**
     * The table of contents of a forum. Only the first page is cached,
     * the subsequent pages are regenerated on each pageview.
     *
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
    public function tableOfContentsForum(int $page = 1): array {
        $key = sprintf(self::CACHE_TOC_FORUM, $this->id);
        $forumToc = null;
        if ($page > 1 || ($page == 1 && !$forumToc = self::$cache->get_value($key))) {
            self::$db->prepared_query("
                SELECT ft.ID, ft.Title, ft.AuthorID, ft.IsLocked, ft.IsSticky,
                    ft.NumPosts, ft.LastPostID, ft.LastPostTime, ft.LastPostAuthorID,
                    (SELECT count(*) from forums_topics WHERE IsSticky = '1' AND ForumID = ?) AS stickyCount,
                    (SELECT count(*) from forums_topics WHERE ForumID = ?) AS threadCount,
                    (fp.TopicID IS NOT NULL AND fp.Closed = '0')  AS has_poll
                FROM forums_topics ft
                LEFT JOIN forums_polls fp ON (fp.TopicID = ft.ID)
                WHERE ft.ForumID = ?
                ORDER BY ft.Ranking DESC, ft.IsSticky DESC, ft.LastPostTime DESC
                LIMIT ?, ?
                ", $this->id, $this->id, $this->id, ($page - 1) * TOPICS_PER_PAGE, TOPICS_PER_PAGE
            );
            $forumToc = self::$db->to_array('ID', MYSQLI_ASSOC, false);
            if ($page == 1) {
                self::$cache->cache_value($key, $forumToc, 86400 * 10);
            }
        }
        return $forumToc;
    }

    public function departmentList(User $user): array {
        $cond = [];
        $args = [];
        $permittedForums = $user->permittedForums();
        if (!$permittedForums) {
            $cond[] = 'f.MinClassRead <= ?';
            $args[] = $user->classLevel();
        } else {
            $cond[] = '(f.MinClassRead <= ? OR f.ID IN (' . placeholders($permittedForums) . '))';
            $args = array_merge([$user->classLevel()], $permittedForums);
        }
        $forbiddenForums = $user->forbiddenForums();
        if ($forbiddenForums) {
            $cond[] = 'f.ID NOT IN (' . placeholders($forbiddenForums) . ')';
            $args = array_merge($args, $forbiddenForums);
        }
        self::$db->prepared_query("
            SELECT f.ID  AS forum_id,
                f.Name   AS name,
                sum(if(ft.LastPostId > coalesce(flrt.PostID, 0) AND C.last_read <= ft.LastPostTime, 1, 0)) AS unread,
                (f.id = origin.id) AS active
            FROM (SELECT coalesce((SELECT last_read FROM user_read_forum WHERE user_id = ?), '2015-01-01 00:00:00') AS last_read) AS C,
                forums_topics ft
            INNER JOIN forums f ON (f.id = ft.ForumID)
            INNER JOIN forums origin USING (CategoryID)
            LEFT JOIN forums_last_read_topics flrt ON (flrt.TopicID = ft.id AND flrt.UserID = ?)
            WHERE origin.ID = ?
                AND " . implode(' AND ', $cond) . "
            GROUP BY ft.ForumID
            ORDER BY f.Sort
            ", $user->id(), $user->id(), $this->id, ...$args
        );
        return self::$db->to_array('forum_id', MYSQLI_ASSOC, false);
    }

    public function userCatchup(User $user): int {
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics
                   (UserID, TopicID, PostID)
            SELECT ?,       ID,      LastPostID
            FROM forums_topics
            WHERE ForumID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $user->id(), $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Return a list of which page the user has read up to
     *
     *  - int 'TopicID' The thread id
     *  - int 'PostID'  The post id
     *  - int 'Page'    The page number
     */
    public function userLastRead(User $user): array {
        self::$db->prepared_query("
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
            ", $user->postsPerPage(), $this->id, $user->id()
        );
        return self::$db->to_array('TopicID', MYSQLI_ASSOC, false);
    }

    public function isAutoSubscribe(User $user): bool {
        return (bool)$this->pg()->scalar("
            SELECT 1
            FROM forum_autosub
            WHERE id_forum = ?
                AND id_user = ?
            ", $this->id, $user->id()
        );
    }

    public function autoSubscribeUserIdList(): array {
        return $this->pg()->column("
            SELECT id_user FROM forum_autosub WHERE id_forum = ?
            ", $this->id
        );
    }

    public function autoSubscribeForUserList(User $user): array {
        if (!$user->permitted('site_forum_autosub')) {
            return [];
        }
        return $this->pg()->column("
            select id_forum from forum_autosub where id_user = ?
            ", $user->id()
        );
    }

    /**
     * Toggle the state of auto subscriptions on this forum
     *
     * return @int 1 if the state actually changed, otherwise 0
     */
    public function toggleAutoSubscribe(User $user, bool $active): int {
        if ($active) {
            return $this->pg()->prepared_query("
                INSERT INTO forum_autosub
                       (id_forum, id_user)
                VALUES (?,        ?)
                ON CONFLICT (id_forum, id_user) DO NOTHING
                ", $this->id, $user->id()
            );
        } else {
            return $this->pg()->prepared_query("
                DELETE FROM forum_autosub
                WHERE id_forum = ?
                    AND id_user = ?
                ", $this->id, $user->id()
            );
        }
    }
}
