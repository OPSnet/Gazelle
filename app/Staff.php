<?php

namespace Gazelle;

class Staff {
    /** @var \DB_MYSQL */
    protected  $db;

    /** @var \CACHE */
    protected  $cache;

    /** @var int */
    protected  $id;

    public function __construct(\DB_MYSQL $db, \CACHE $cache, int $id) {
        $this->db = $db;
        $this->cache = $cache;
        $this->id = $id;
    }

    public function id() {
        return $this->id;
    }

    public function blogAlert() {
        if (($readTime = $this->cache->get_value('staff_blog_read_'. $this->id)) === false) {
            $readTime = $this->db->scalar('
                SELECT unix_timestamp(Time)
                FROM staff_blog_visits
                WHERE UserID = ?
                ', $this->id
            ) ?? 0;
            $this->cache->cache_value('staff_blog_read_' . $this->id, $readTime, 1209600);
        }
        if (($blogTime = $this->cache->get_value('staff_blog_latest_time')) === false) {
            $blogTime = $this->db->scalar('
                SELECT unix_timestamp(max(Time))
                FROM staff_blog
                '
            ) ?? 0;
            $this->cache->cache_value('staff_blog_latest_time', $blogTime, 1209600);
        }
        return $readTime < $blogTime;
    }
}
