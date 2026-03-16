<?php

namespace Gazelle;

class Blog extends BasePgObject {
    final public const tableName = 'blog';
    final public const pkName    = 'id_blog';
    final public const CACHE_KEY = 'blogv2_2_%d';

    public function location(): string {
        return "blog.php?id={$this->id}#blog{$this->id}";
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title()));
    }

    public function flush(): static {
        self::$cache->deleteMulti([
            Manager\Blog::CACHE_KEY,
            sprintf(self::CACHE_KEY, $this->id),
        ]);
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = $this->pg()->rowAssocex('
                select title,
                    body,
                    id_thread,
                    created,
                    id_user
                from blog
                where id_blog = $1
                ', $this->id
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
    public function created(): \DateTimeImmutable {
        return $this->info()['created'];
    }

    /**
     * The creation epoch of the blog
     */
    public function createdEpoch(): int {
        return $this->created()->getTimestamp();
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
        return $this->info()['id_thread'];
    }

    /**
     * The forum thread ID of the blog
     */
    public function thread($manager = new Manager\ForumThread()): ?ForumThread {
        return $manager->findById((int)$this->threadId());
    }

    /**
     * The author of the blog
     */
    public function userId(): int {
        return $this->info()['id_user'];
    }

    /**
     * Remove an existing blog article, and thread if it exists
     */
    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM user_read_blog WHERE blog_id = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        return $affected + $this->thread()?->remove() + parent::remove();
    }
}
