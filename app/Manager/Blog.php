<?php

namespace Gazelle\Manager;

class Blog extends \Gazelle\Base {

    const CACHE_KEY = 'blogv2';

    /**
     * Create a blog article
     * @param array
     *      - userId     The UserID of the author
     *      - title      The title of the article
     *      - body       The body of the article
     *      - threadId   The associated threadId
     *      - important  The level of importance
     * @return ID of new article
     */
    public function create(array $info): int {
        $this->db->prepared_query("
            INSERT INTO blog
                   (UserID, Title, Body, ThreadID, Important)
            VALUES (?,      ?,     ?,    ?,        ?)
            ", $info['userId'], trim($info['title']), trim($info['body']), $info['threadId'], $info['important']
        );
        $this->cache->deleteMulti(['feed_blog', self::CACHE_KEY]);
        return $this->db->inserted_id();
    }

    /**
     * Modify an existing blog article
     *
     * @param array
     *      - id         The id of the news article
     *      - title      The title of the article
     *      - body       The body of the article
     *      - threadId   The associated threadId
     *      - important  The level of importance
     * @return 1 if successful
     */
    public function modify(array $info): int {
        $this->db->prepared_query("
            UPDATE blog SET
                Title = ?,
                Body = ?,
                ThreadID = ?,
                Important = ?
            WHERE ID = ?
            ", trim($info['title']), trim($info['body']), $info['threadId'], $info['important'], $info['id']
        );
        $this->cache->deleteMulti(['feed_blog', self::CACHE_KEY]);
        return $this->db->affected_rows();
    }

    /**
     * Get a number of most recent articles.
     * (hard-coded to 5 max, otherwise cache invalidation becomes difficult)
     *
     * @return array
     *      - id of article
     *      - name of author
     *      - id of author
     *      - title of article
     *      - body of article
     *      - article creation date
     *      - threadId of associated thread
     */
    public function headlines(): array {
        if (($headlines = $this->cache->get_value(self::CACHE_KEY)) === false) {
            $this->db->prepared_query("
                SELECT b.ID, um.Username, b.UserID, b.Title, b.Body, b.Time, b.ThreadID
                FROM blog b
                INNER JOIN users_main um ON (um.ID = b.UserID)
                WHERE b.Time < now()
                ORDER BY b.Time DESC
                LIMIT 5
            ");
            $headlines = $this->db->to_array(false, MYSQLI_NUM, false);
            $this->cache->cache_value(self::CACHE_KEY, $headlines, 0);
        }
        return $headlines;
    }

    /**
     * Get the latest blog article id and title
     * ID will be -1 if no article yet exists.
     *
     * @return array [$id, $title]
     */
    public function latest(): array {
        $headlines = $this->headlines();
        [$blogId, $title] = $headlines[0];
        if (!$blogId) {
            $blogId = -1;
            $title = '';
        }
        return [$blogId, $title];
    }

    /**
     * Get the latest blog article id
     * ID will be -1 if no article yet exists.
     *
     * @return int $id blog article id
     */
    public function latestId(): int {
        [$blogId] = $this->latest();
        return $blogId;
    }
}
