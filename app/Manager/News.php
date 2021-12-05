<?php

namespace Gazelle\Manager;

class News extends \Gazelle\Base {

    const CACHE_KEY = 'newsv2';

    /**
     * Create a news article
     *
     * @param $userId  The UserID of the author
     * @param $title   The title of the article
     * @param $body    The body of the article
     * @return ID of new article
     */
    public function create(int $userId, string $title, string $body): int {
        self::$db->prepared_query("
            INSERT INTO news
                   (UserID, Title, Body)
            VALUES (?,      ?,     ?)
            ", $userId, trim($title), trim($body)
        );
        self::$cache->deleteMulti(['feed_news', self::CACHE_KEY]);
        return self::$db->inserted_id();
    }

    /**
     * Modify an existing news article (the author remains unchanged)
     *
     * @param $id      The article ID
     * @param $title   The title of the article
     * @param $body    The body of the article
     * @return 1 if successful
     */
    public function modify(int $id, string $title, string $body): int {
        self::$db->prepared_query("
            UPDATE news SET
                Title = ?,
                Body = ?
            WHERE ID = ?
            ", trim($title), trim($body), $id
        );
        self::$cache->deleteMulti(['feed_news', self::CACHE_KEY]);
        return self::$db->affected_rows();
    }

    /**
     * Remove an existing news article
     *
     * @param $id The id of the news article
     * @return 1 if successful
     */
    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM news WHERE ID = ?
            ", $id
        );
        self::$cache->deleteMulti(['feed_news', self::CACHE_KEY]);
        return self::$db->affected_rows();
    }

    /**
     * Get a number of most recent articles.
     * (hard-coded to 20 max, otherwise cache invalidation becomes difficult)
     *
     * @return array
     *      - id of article
     *      - title of article
     *      - body of article
     *      - article creation date
     */
    public function headlines(): array {
        if (($headlines = self::$cache->get_value(self::CACHE_KEY)) === false) {
            self::$db->prepared_query("
                SELECT ID, Title, Body, Time
                FROM news
                ORDER BY Time DESC
                LIMIT 20
            ");
            $headlines = self::$db->to_array(false, MYSQLI_NUM, false);
            self::$cache->cache_value(self::CACHE_KEY, $headlines, 0);
        }
        return $headlines;
    }

    /**
     * Get the title and body of an article
     *
     * @param $id ID of article
     * @return array [string $title, string $body] or null if no such article
     *
     */
    public function fetch(int $id): array {
        return self::$db->row("
            SELECT Title, Body
            FROM news
            WHERE ID = ?
            ", $id
        );
    }

    /**
     * Get the latest news article id and title
     * ID will be -1 if no news yet exists.
     *
     * @return array [$id, $title]
     */
    public function latest(): array {
        $headlines = $this->headlines();
        return $headlines[0] ?? [-1, null, null, null];
    }

    /**
     * Get the latest news article id
     * ID will be -1 if no news yet exists.
     *
     * @return int $id news article id
     */
    public function latestId(): int {
        [$newsId] = $this->latest();
        return $newsId;
    }
}
