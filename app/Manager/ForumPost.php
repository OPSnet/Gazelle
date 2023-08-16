<?php

namespace Gazelle\Manager;

class ForumPost extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_fp_%d';

    /**
     * Create a forum post
     */
    public function create(int $threadId, int $userId, string $body): \Gazelle\ForumPost {
        self::$db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            Values (?,       ?,        ?)
            ", $threadId, $userId, $body
        );
        return $this->findById(self::$db->inserted_id());
    }

    /**
     * Instantiate a post by its ID
     */
    public function findById(int $postId): ?\Gazelle\ForumPost {
        $key = sprintf(self::ID_KEY, $postId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM forums_posts WHERE ID = ?
                ", $postId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\ForumPost($id) : null;
    }
}
