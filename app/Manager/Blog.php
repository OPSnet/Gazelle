<?php

namespace Gazelle\Manager;

class Blog extends \Gazelle\BaseManager {
    final const CACHE_KEY = 'blog';
    final const ID_KEY    = 'zz_blog_%d';

    public function flush(): static {
        self::$cache->delete_multi(['feed_blog', self::CACHE_KEY]);
        return $this;
    }

    /**
     * Create a blog article
     */
    public function create(array $info): \Gazelle\Blog {
        self::$db->prepared_query("
            INSERT INTO blog
                   (UserID, Title, Body, ThreadID, Important)
            VALUES (?,      ?,     ?,    ?,        ?)
            ", $info['userId'], trim($info['title']), trim($info['body']), $info['threadId'], $info['important']
        );
        $this->flush();
        return new \Gazelle\Blog(self::$db->inserted_id());
    }

    public function findById(int $blogId): ?\Gazelle\Blog {
        $key = sprintf(self::ID_KEY, $blogId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM blog WHERE ID = ?
                ", $blogId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, (int)$id, 7200);
            }
        }
        return $id ? new \Gazelle\Blog((int)$id) : null;
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
            self::$db->prepared_query("
                SELECT b.ID
                FROM blog b
                ORDER BY b.Time DESC
                LIMIT 20
            ");
            $idList = self::$db->collect(0, false);
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
     * ID will be 0 if no article yet exists.
     *
     * @return int $id blog article id
     */
    public function latestId(): int {
        $latest = $this->latest();
        return $latest ? $latest->id() : 0;
    }

    /**
     * Get the epoch of the most recent entry
     * epoch will be 0 if no article yet exists.
     */
    public function latestEpoch(): int {
        $latest = $this->latest();
        return $latest ? (int)strtotime($latest->created()) : 0;
    }
}
