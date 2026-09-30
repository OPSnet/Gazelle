<?php

namespace Gazelle;

class Staff extends BaseUser {
    final public const tableName = 'staff_blog_visits';

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    public function link(): string {
        return $this->user()->link();
    }

    public function location(): string {
        return $this->user()->location();
    }

    public function blogAlert(): bool {
        if (($readTime = self::$cache->get_value('staff_blog_read_' . $this->user->id)) === false) {
            $readTime = self::$db->scalar('
                SELECT unix_timestamp(Time)
                FROM staff_blog_visits
                WHERE UserID = ?
                ', $this->user->id
            ) ?? 0;
            self::$cache->cache_value('staff_blog_read_' . $this->user->id, $readTime, 1_209_600);
        }
        if (($blogTime = self::$cache->get_value('staff_blog_latest_time')) === false) {
            $blogTime = self::$db->scalar('
                SELECT unix_timestamp(max(Time))
                FROM staff_blog
                '
            ) ?? 0;
            self::$cache->cache_value('staff_blog_latest_time', $blogTime, 1_209_600);
        }
        return $readTime < $blogTime;
    }
}
