<?php

namespace Gazelle\User\Notification;

class StaffBlog extends AbstractNotification {
    public function className(): string {
        return 'information';
    }

    public function clear(): int {
        return 0;
    }

    public function load(): bool {
        if (!$this->user->permitted('users_mod')) {
            return false;
        }

        $readTime = self::$cache->get_value('staff_blog_read_' . $this->user->id());
        if ($readTime === false) {
            $readTime = (int)self::$db->scalar("
                SELECT unix_timestamp(Time) FROM staff_blog_visits WHERE UserID = ?
                ", $this->user->id()
            );
            self::$cache->cache_value('staff_blog_read_' . $this->user->id(), $readTime, 0);
        }
        $latestTime = self::$cache->get_value('staff_blog_latest_time');
        if ($latestTime === false) {
            $latestTime = (int)self::$db->scalar("
                SELECT unix_timestamp(max(Time)) FROM staff_blog
            ");
            self::$cache->cache_value('staff_blog_latest_time', $latestTime, 0);
        }

        if ($readTime < $latestTime) {
            $this->title = 'New Staff Blog Post!';
            $this->url   = 'staffblog.php';
            return true;
        }
        return false;
    }
}
