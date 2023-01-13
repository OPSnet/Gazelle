<?php

namespace Gazelle;

class Blog extends BaseObject {
    const CACHE_KEY = 'blog_%d';

    public function tableName(): string { return 'blog'; }

    public function location(): string {
        return 'blog.php?id=' . $this->id . '#blog' . $this->id;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title()));
    }

    public function flush(): Blog {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        return $this;
    }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT Title  AS title,
                    Body      AS body,
                    ThreadID  AS thread_id,
                    Time      AS created,
                    UserID    AS user_id,
                    Important AS important
                FROM blog
                WHERE ID = ?
                ", $this->id
            );
            self::$cache->cache_value($key, $info, 7200);
        }
        $this->info = $info;
        return $this->info;
    }

    /**
     * The body of the blog
     */
    public function body(): string {
        return $this->info()['body'];
    }

    /**
     * The creation date of the blog
     */
    public function created(): string {
        return $this->info()['created'];
    }

    /**
     * The creation epoch of the blog
     */
    public function createdEpoch(): int {
        return strtotime($this->info()['created']);
    }

    /**
     * The importance of the blog
     */
    public function important(): int {
        return $this->info()['important'];
    }

    /**
     * The title of the blog
     */
    public function title(): string {
        return $this->info()['title'];
    }

    /**
     * The forum thread ID of the blog
     */
    public function threadId(): ?int {
        return $this->info()['thread_id'];
    }

    /**
     * The author of the blog
     */
    public function userId(): int {
        return $this->info()['user_id'];
    }

    /**
     * Remove an existing blog article
     */
    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM blog WHERE ID = ?
            ", $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    /**
     * Remove an the link to the forum topic of the blog article
     */
    public function removeThread(): int {
        self::$db->prepared_query("
            UPDATE blog SET
                ThreadID = NULL
            WHERE ID = ?
            ", $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }
}
