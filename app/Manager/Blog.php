<?php

namespace Gazelle\Manager;

class Blog extends \Gazelle\Base {

    const CACHE_KEY = 'blogv2';

    public function flushCache() {
        self::$cache->deleteMulti(['feed_blog', self::CACHE_KEY]);
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
        $this->flushCache();
        return new \Gazelle\Blog(self::$db->inserted_id());
    }

    /**
     * Modify an existing blog article
     */
    public function modify(array $info): int {
        self::$db->prepared_query("
            UPDATE blog SET
                Title = ?,
                Body = ?,
                ThreadID = ?,
                Important = ?
            WHERE ID = ?
            ", trim($info['title']), trim($info['body']), $info['threadId'], $info['important'], $info['id']
        );
        $this->flushCache();
        return self::$db->affected_rows();
    }

    /**
     * Remove an existing blog article
     */
    public function remove(int $blogId): bool {
        self::$db->prepared_query("
            DELETE FROM blog WHERE ID = ?
            ", $blogId
        );
        $removed = self::$db->affected_rows() == 1;
        if ($removed) {
            $this->flushCache();
        }
        return $removed;
    }

    /**
     * Remove an the link to the forum topic of the blog article
     */
    public function removeThread(int $blogId): bool {
        self::$db->prepared_query("
            UPDATE blog SET
                ThreadID = NULL
            WHERE ID = ?
            ", $blogId
        );
        $removed = self::$db->affected_rows() == 1;
        if ($removed) {
            $this->flushCache();
        }
        return $removed;
    }

    /**
     * Get a number of most recent articles.
     * (hard-coded to 20 max, otherwise cache invalidation becomes difficult)
     *
     * @return array
     *      - id of article
     *      - title of article
     *      - name of author
     *      - id of author
     *      - body of article
     *      - article creation date
     *      - threadId of associated thread
     */
    public function headlines(): array {
        if (($headlines = self::$cache->get_value(self::CACHE_KEY)) === false) {
            self::$db->prepared_query("
                SELECT b.ID, b.Title, um.Username, b.UserID, b.Body, b.Time, b.ThreadID
                FROM blog b
                INNER JOIN users_main um ON (um.ID = b.UserID)
                ORDER BY b.Time DESC
                LIMIT 20
            ");
            $headlines = self::$db->to_array(false, MYSQLI_NUM, false);
            self::$cache->cache_value(self::CACHE_KEY, $headlines, 86400);
        }
        return $headlines;
    }

    /**
     * Get the latest blog article id and title
     * ID will be null if no article yet exists.
     *
     * @return array [$id, $title]
     */
    public function latest(): array {
        $headlines = $this->headlines();
        return $headlines ? $headlines[0] : [null, null];
    }

    /**
     * Get the latest blog article id
     * ID will be null if no article yet exists.
     *
     * @return int $id blog article id
     */
    public function latestId(): ?int {
        return $this->latest()[0];
    }

    /**
     * Get the epoch of the most recent entry
     */
    public function latestEpoch(): int {
        $latest = $this->latest();
        return isset($latest[5]) ? strtotime($latest[5]) : 0;
    }
}
