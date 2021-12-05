<?php

namespace Gazelle\Manager;

class StaffBlog extends \Gazelle\Base {
    /** @var int */
    protected $blogId;

    /** @var int */
    protected $authorId;

    /** @var string */
    protected $body;

    /** @var string */
    protected $title;

    protected const CACHE_KEY = 'staff_blog';
    protected const CACHE_READ_KEY = 'staff_blog_read_%d';

    /**
     * Update the last visited timestamp
     *
     * @param int ID of the vistort
     */
    public function visit(int $userId) {
        self::$db->prepared_query("
            INSERT INTO staff_blog_visits
                   (UserID)
            VALUES (?)
            ON DUPLICATE KEY UPDATE Time = now()
            ", $userId
        );
        self::$cache->delete_value(sprintf(self::CACHE_READ_KEY, $userId));
    }

    public function blogId(): ?int {
        return $this->blogId;
    }

    public function authorId(): ?int {
        return $this->authorId;
    }

    public function body(): ?string {
        return $this->body;
    }

    public function title(): ?string {
        return $this->title;
    }

    public function load(int $blogId) {
        $this->blogId = $blogId;
        [$this->body, $this->title] = self::$db->row("
            SELECT Body, Title
            FROM staff_blog
            WHERE ID = ?
            ", $this->blogId
        );
        return $this;
    }

    /**
     * Get the list of blog entries, most recent first
     *
     * @return array list of entries
     */
    public function blogList(): array {
        if (($list = self::$cache->get_value(self::CACHE_KEY)) === false) {
            self::$db->prepared_query("
                SELECT
                    b.ID        AS id,
                    um.Username AS author,
                    b.Title     AS title,
                    b.Body      AS body,
                    b.Time      AS created,
                    unix_timestamp(b.Time) as epoch
                FROM staff_blog AS b
                INNER JOIN users_main AS um ON (b.UserID = um.ID)
                ORDER BY Time DESC
            ");
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::CACHE_KEY, $list, 1209600);
        }
        return $list;
    }

    /**
     * When was the blog read by a user?
     *
     * @param int user id
     * @return int epoch
     */
    public function readBy(\Gazelle\User $user) {
        $key = sprintf(self::CACHE_READ_KEY, $user->id());
        if (($time = self::$cache->get_value($key)) === false) {
            $time = self::$db->scalar("
                SELECT Time FROM staff_blog_visits WHERE UserID = ?
                ", $user->id()
            );
            $time = $time ? strtotime($time) : 0;
            self::$cache->cache_value($key, $time, 86400);
        }
        return $time;
    }

    /**
     * Set the ID of a blog post. If this is set, calling modify()
     * will issue an update, rather than an insert.
     *
     * @param int blog id
     */
    public function setId(int $blogId) {
        $this->blogId = $blogId;
        return $this;
    }

    /**
     * Set the author user ID of a blog post. Used during creation of a post.
     *
     * @param int user id
     */
    public function setAuthorId(int $userId) {
        $this->authorId = $userId;
        return $this;
    }

    /**
     * Set the title of a blog post
     *
     * @param string title of the blog post
     */
    public function setTitle(string $title) {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the body of a blog post
     *
     * @param string body of the blog post
     */
    public function setBody(string $body) {
        $this->body = $body;
        return $this;
    }

    /**
     * Save the changes of the blog post.
     *
     * @return bool success
     */
    public function modify() {
        if ($this->blogId) {
            self::$db->prepared_query("
                UPDATE staff_blog SET
                    Title = ?,
                    Body = ?
                WHERE ID = ?
                ", $this->title, $this->body, $this->blogId
            );
        } else {
            self::$db->prepared_query("
                INSERT INTO staff_blog
                       (UserID, Title, Body)
                VALUES (?,      ?,     ?)
                ", $this->authorId, $this->title, $this->body
            );
            $this->blogId = self::$db->inserted_id();
        }
        self::$cache->deleteMulti(['staff_feed_blog', self::CACHE_KEY]);
        return self::$db->affected_rows() === 1;
    }

    /**
     * Remove a blog post.
     *
     * @return bool success
     */
    public function remove(int $blogId) {
        self::$db->prepared_query("
            DELETE FROM staff_blog WHERE ID = ?
            ", $blogId
        );
        $this->blogId = null;
        $this->body = null;
        $this->title = null;
        self::$cache->deleteMulti(['staff_feed_blog', self::CACHE_KEY]);
        return self::$db->affected_rows() === 1;
    }
}
