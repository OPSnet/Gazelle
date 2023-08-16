<?php

namespace Gazelle\Manager;

class ForumThread extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_ft_%d';

    /**
     * Create a forum thread
     */
    public function create(\Gazelle\Forum $forum, int $userId, string $title, string $body): \Gazelle\ForumThread {
        $db = new \Gazelle\DB;
        $db->relaxConstraints(true);
        self::$db->prepared_query("
            INSERT INTO forums_topics
                   (ForumID, Title, AuthorID, LastPostAuthorID)
            Values (?,       ?,        ?,                ?)
            ", $forum->id(), $title, $userId, $userId
        );
        $thread = $this->findById(self::$db->inserted_id());
        $thread->addPost($userId, $body);
        $db->relaxConstraints(false);
        (new \Gazelle\Stats\User($userId))->increment('forum_thread_total');
        $forum->flush();
        return $thread;
    }

    /**
     * Instantiate a thread by its ID
     */
    public function findById(int $threadId): ?\Gazelle\ForumThread {
        $key = sprintf(self::ID_KEY, $threadId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM forums_topics WHERE ID = ?
                ", $threadId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\ForumThread($id) : null;
    }

    /**
     * Find the thread from a post ID.
     */
    public function findByPostId(int $postId): ?\Gazelle\ForumThread {
        $id = self::$db->scalar("
            SELECT TopicID FROM forums_posts WHERE ID = ?
            ", $postId
        );
        return $id ? new \Gazelle\ForumThread($id) : null;
    }

    public function lockOldThreads(): int {
        self::$db->prepared_query("
            SELECT t.ID
            FROM forums_topics AS t
            INNER JOIN forums AS f ON (t.ForumID = f.ID)
            WHERE t.IsLocked = '0'
                AND t.IsSticky = '0'
                AND f.AutoLock = '1'
                AND t.LastPostTime + INTERVAL f.AutoLockWeeks WEEK < now()
        ");

        $ids = self::$db->collect('ID');
        if ($ids) {
            $placeholders = placeholders($ids);
            self::$db->prepared_query("
                UPDATE forums_topics SET
                    IsLocked = '1'
                WHERE ID IN ($placeholders)
            ", ...$ids);

            self::$db->prepared_query("
                DELETE FROM forums_last_read_topics
                WHERE TopicID IN ($placeholders)
            ", ...$ids);

            foreach ($ids as $id) {
                $thread = $this->findById($id);
                $thread->addThreadNote(0, 'Locked automatically by schedule');
                $thread->flush();
                $thread->forum()->flush();
            }
        }
        return count($ids);
    }
}
