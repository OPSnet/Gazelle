<?php

namespace Gazelle;

class ForumCategory extends BaseObject {
    final const CACHE_KEY = 'forum_cat_%d';

    protected array $info;

    public function flush(): ForumCategory {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        self::$cache->delete_value(Manager\ForumCategory::LIST_KEY);
        $this->info = [];
        return $this;
    }
    public function tableName(): string { return 'forums_categories'; }
    public function link(): string { return "<a href=\"{$this->location()}\">Forum Categories</a>"; }
    public function location(): string { return "tools.php?action=categories"; }

    public function info(): array {
        if (!empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT Name AS name,
                    Sort AS sequence
                FROM forums_categories
                WHERE ID = ?
                ", $this->id
            );
            self::$cache->cache_value($key, $info, 86400);
        }
        $this->info = $info;
        return $this->info;
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function sequence(): int {
        return $this->info()['sequence'];
    }

    public function forumTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM forums WHERE CategoryID = ?
            ", $this->id
        );
    }

    public function remove(): int {
        if ($this->forumTotal()) {
            // still in use
            return 0;
        }
        self::$db->prepared_query("
            DELETE FROM forums_categories WHERE ID = ?
            ", $this->id
        );
        self::$cache->delete_value(Manager\ForumCategory::LIST_KEY);
        return self::$db->affected_rows();
    }
}
