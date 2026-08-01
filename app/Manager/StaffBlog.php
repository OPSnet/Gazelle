<?php

namespace Gazelle\Manager;

class StaffBlog extends \Gazelle\Base {
    final public const CACHE_KEY = 'sblog';
    protected const CACHE_READ_KEY = 'staff_blog_read_%d';

    public function flush(): static {
        self::$cache->delete_value(self::CACHE_KEY);
        return $this;
    }

    public function create(\Gazelle\User $user, string $title, string $body): \Gazelle\StaffBlog {
        self::$db->prepared_query("
            INSERT INTO staff_blog
                   (UserID, Title, Body)
            Values (?,      ?,     ?)
            ", $user->id, $title, $body
        );
        $id = self::$db->inserted_id();
        $this->flush();
        return $this->findById($id);
    }

    public function findById(int $id): ?\Gazelle\StaffBlog {
        $staffBlogId = (int)self::$db->scalar("
            SELECT ID FROM staff_blog WHERE ID = ?
            ", $id
        );
        return $staffBlogId ? new \Gazelle\StaffBlog($staffBlogId) : null;
    }

    /**
     * Get the list of blog entries, most recent first
     */
    public function blogList(): array {
        $list = self::$cache->get_value(self::CACHE_KEY);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT sb.ID AS id
                FROM staff_blog sb
                ORDER BY Time DESC
            ");
            $list = self::$db->collect(0);
            self::$cache->cache_value(self::CACHE_KEY, $list, 1_209_600);
        }
        return array_map($this->findById(...), $list);
    }

    /**
     * When was the blog read by a user?
     *
     * @return int epoch
     */
    public function readBy(\Gazelle\User $user): int {
        $key = sprintf(self::CACHE_READ_KEY, $user->id);
        $time = self::$cache->get_value($key);
        if ($time === false) {
            $time = self::$db->scalar("
                SELECT Time FROM staff_blog_visits WHERE UserID = ?
                ", $user->id
            );
            $time = $time ? (int)strtotime((string)$time) : 0;
            self::$cache->cache_value($key, $time, 86400);
        }
        return $time;
    }

    /**
     * Update the last visited timestamp
     */
    public function catchup(\Gazelle\User $user): int {
        self::$db->prepared_query("
            INSERT INTO staff_blog_visits
                   (UserID)
            VALUES (?)
            ON DUPLICATE KEY UPDATE Time = now()
            ", $user->id
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value(sprintf(self::CACHE_READ_KEY, $user->id));
        return $affected;
    }
}
