<?php

namespace Gazelle\Manager;

class Blog extends \Gazelle\BaseManager {
    final public const CACHE_KEY = 'blogv2';
    final protected const ID_KEY = 'zz_blog_%d';

    public function flush(): static {
        self::$cache->delete_multi(['feed_blog', self::CACHE_KEY]);
        return $this;
    }

    /**
     * Create a blog article
     */
    public function create(
        string                    $title,
        string                    $body,
        \Gazelle\ForumThread|null $thread,
        \Gazelle\User             $user,
    ): \Gazelle\Blog {
        $id = $this->pg()->scalarex('
            insert into blog
                   (id_user, title, body, id_thread)
            values ($1,      $2,    $3,   $4)
            returning id_blog
            ', $user->id, $title, $body, $thread?->id,
        );
        $this->flush();
        return new \Gazelle\Blog($id);
    }

    public function findById(int $id): ?\Gazelle\Blog {
        $key = sprintf(self::ID_KEY, $id);
        $blogId = self::$cache->get_value($key);
        if ($blogId === false) {
            $blogId = (int)$this->pg()->scalarex('
                select id_blog from blog where id_blog = $1
                ', $id
            );
            if ($blogId) {
                self::$cache->cache_value($key, $blogId, 7200);
            }
        }
        return $blogId ? new \Gazelle\Blog($blogId) : null;
    }

    /**
     * Get a number of most recent articles.
     * (hard-coded to 20 max, otherwise cache invalidation becomes difficult)
     *
     * @return array of \Gazelle\Blog instances
     */
    public function headlines(): array {
        $idList = self::$cache->get_value(self::CACHE_KEY);
        if ($idList === false) {
            $idList = $this->pg()->columnex("
                select b.id_blog
                from blog b
                order by b.created desc
                limit 20
            ");
            self::$cache->cache_value(self::CACHE_KEY, $idList, 7200);
        }
        return array_map(fn ($id) => new \Gazelle\Blog($id), $idList);
    }

    /**
     * Get the latest blog article
     * will be null if no article yet exists.
     *
     * @return \Gazelle\Blog or null
     */
    public function latest(): ?\Gazelle\Blog {
        $headlines = $this->headlines();
        return $headlines ? $headlines[0] : null;
    }

    /**
     * Get the latest blog article id
     * ID will be null if no article yet exists.
     *
     * @return int $id blog article id
     */
    public function latestId(): ?int {
        return $this->latest()?->id;
    }

    /**
     * Get the epoch of the most recent entry
     * epoch will be null if no article yet exists.
     */
    public function latestEpoch(): ?int {
        return $this->latest()?->createdEpoch();
    }
}
