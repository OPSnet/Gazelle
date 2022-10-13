<?php

namespace Gazelle;

class ForumPost extends BaseObject {

    const CACHE_KEY     = 'fpost_%d';

    protected array $info;

    public function tableName(): string {
        return 'forums_posts';
    }

    public function flush() {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        $this->thread()->flush();
    }

    public function location(): string {
        return "forums.php?action=viewthread&threadid={$this->threadId()}&postid={$this->id}#post{$this->id}";
    }

    public function url(): string {
        return htmlentities($this->location());
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), "Post #{$this->id}");
    }

    /**
     * Get information about a post
     */
    public function info(): array {
        if (!empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT f.ID                 AS forum_id,
                    f.MinClassWrite         AS min_class_write,
                    t.ID                    AS thread_id,
                    t.Islocked = '1'        AS thread_locked,
                    ceil(t.NumPosts / ?)    AS thread_page_total,
                    cast((SELECT ceil(sum(if(fp.ID <= p.ID, 1, 0)) / ?) FROM forums_posts fp WHERE fp.TopicID = t.ID) AS signed)
                                            AS page,
                    p.AuthorID              AS user_id,
                    (p.ID = t.StickyPostID) AS is_sticky,
                    p.Body                  AS body,
                    p.AddedTime             AS created,
                    p.EditedUserID          AS edit_user_id,
                    p.EditedTime            AS edit_time
                FROM forums_topics      t
                INNER JOIN forums       f ON (t.forumid = f.id)
                INNER JOIN forums_posts p ON (p.topicid = t.id)
                WHERE p.ID = ?
                ", POSTS_PER_PAGE, POSTS_PER_PAGE, $this->id
            );
            self::$cache->cache_value($key, $info, 86400);
        }
        $this->info = $info;
        return $this->info;
    }

    public function thread(): ForumThread {
        return new ForumThread($this->threadId());
    }

    public function threadId(): int {
        return $this->info()['thread_id'];
    }

    public function userId(): int {
        return $this->info()['user_id'];
    }

    public function body(): string {
        return $this->info()['body'];
    }

    public function isPinned(): bool {
        return (bool)$this->info()['is_pinned'];
    }

    public function isSticky(): bool {
        return (bool)$this->info()['is_sticky'];
    }

    public function page(): int {
        return $this->info()['page'];
    }

    public function threadPageTotal(): int {
        return $this->info()['thread_page_total'];
    }

    public function priorPostTotal(): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM forums_posts
            WHERE TopicID = (SELECT TopicID FROM forums_posts WHERE ID = ?)
                AND ID <= ?
            ", $this->id, $this->id
        );
    }

    public function edit(int $userId, string $body): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO comments_edits
                   (EditUser, PostID, Body, Page)
            VALUES (?,        ?,      (SELECT Body from forums_posts WHERE ID = ?), 'forums')
            ", $userId, $this->id, $this->id
        );
        self::$db->prepared_query("
            UPDATE forums_posts SET
                EditedUserID = ?,
                Body = ?,
                EditedTime = now()
            WHERE ID = ?
            ", $userId, $body, $this->id
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();

        $this->flush();
        $this->info();
        $this->info['body'] = $body;
        return $affected;
    }

    /**
     * Remove a post from a thread
     */
    public function remove(): bool {
        self::$db->begin_transaction();
        $db = new DB;
        $db->relaxConstraints(true);
        self::$db->prepared_query("
            DELETE fp, unq
            FROM forums_posts fp
            LEFT JOIN users_notify_quoted unq ON (unq.PostID = fp.ID and unq.Page = 'forums')
            WHERE fp.ID = ?
            ", $this->id()
        );
        if (self::$db->affected_rows() === 0) {
            $db->relaxConstraints(false);
            self::$db->rollback();
            return false;
        }

        $thread = $this->thread();
        $threadId = $thread->id();
        self::$db->prepared_query("
            UPDATE forums_topics t
            INNER JOIN
            (
                SELECT
                    count(p.ID) AS NumPosts,
                    IF(t.StickyPostID = ?, 0, t.StickyPostID) AS StickyPostID,
                    t.ID
                FROM forums_topics t
                LEFT JOIN forums_posts p ON (p.TopicID = t.ID) where t.id = ?
            ) UPD ON (UPD.ID = t.ID)
            LEFT JOIN (
                SELECT ID, AuthorID, AddedTime, TopicID
                FROM forums_posts
                WHERE TopicID = ?
                ORDER BY ID desc
                LIMIT 1
            ) LAST ON (LAST.TopicID = UPD.ID)
            SET
                t.NumPosts         = UPD.NumPosts,
                t.StickyPostID     = UPD.StickyPostID,
                t.LastPostID       = LAST.ID,
                t.LastPostAuthorID = LAST.AuthorID,
                t.LastPostTime     = LAST.AddedTime
            WHERE t.ID = ?
            ", $this->id(), $threadId, $threadId, $threadId
        );
        $db->relaxConstraints(false);
        self::$db->commit();

        $this->thread()->forum()->adjust();
        (new \Gazelle\Manager\Subscription)->flush('forums', $threadId);

        $thread->flush();
        $pageOffset = $this->page() - 1;
        $lastOffset = $this->threadPageTotal() - 1;
        if ($pageOffset || $pageOffset < $lastOffset) {
            $thread->flushCatalog(begin: $pageOffset, end: $lastOffset);
        }
        return true;
    }
}
