<?php

namespace Gazelle;

class StaffBlog extends BaseObject {
    final const tableName = 'staff_blog';

    public function flush(): StaffBlog {
        $this->info = [];
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title())); }
    public function location(): string { return 'staffblog.php#blog' . $this->id; }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $this->info = self::$db->rowAssoc("
            SELECT b.UserID AS user_id,
                b.Title     AS title,
                b.Body      AS body,
                b.Time      AS created,
                unix_timestamp(b.Time) as epoch
            FROM staff_blog AS b
            WHERE b.ID = ?
            ", $this->id
        ) ?? [];
        return $this->info;
    }

    public function userId(): int {
        return $this->info()['user_id'];
    }

    public function body(): string {
        return $this->info()['body'];
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function epoch(): int {
        return $this->info()['epoch'];
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM staff_blog WHERE ID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_multi(['staff_feed_blog', Manager\StaffBlog::CACHE_KEY]);
        return $affected;
    }
}
