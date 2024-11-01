<?php

namespace Gazelle\Manager;

class ForumPost extends \Gazelle\BaseManager {
    /**
     * Create a forum post
     */
    public function create(\Gazelle\ForumThread $thread, \Gazelle\User $user, string $body): \Gazelle\ForumPost {
        self::$db->prepared_query("
            INSERT INTO forums_posts
                   (TopicID, AuthorID, Body)
            Values (?,       ?,        ?)
            ", $thread->id(), $user->id(), $body
        );
        return new \Gazelle\ForumPost(self::$db->inserted_id());
    }

    /**
     * Instantiate a post by its ID
     */
    public function findById(int $postId): ?\Gazelle\ForumPost {
        $id = self::$db->scalar("
            SELECT ID FROM forums_posts WHERE ID = ?
            ", $postId
        );
        return $id ? new \Gazelle\ForumPost((int)$id) : null;
    }
}
