<?php

namespace Gazelle\Manager;

use Gazelle\Enum\UserSummary;
use Gazelle\Enum\UserSummaryMisc;

class ForumThread extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_ft_%d';

    /**
     * Create a forum thread
     */
    public function create(\Gazelle\Forum $forum, \Gazelle\User $user, string $title, string $body): \Gazelle\ForumThread {
        $db = new \Gazelle\DB();
        $db->relaxConstraints(true);
        self::$db->prepared_query("
            INSERT INTO forums_topics
                   (ForumID, Title, AuthorID, LastPostAuthorID)
            Values (?,       ?,        ?,                ?)
            ", $forum->id, $title, $user->id, $user->id
        );
        $thread = new \Gazelle\ForumThread(self::$db->inserted_id());
        $thread->addPost($user, $body);
        $db->relaxConstraints(false);
        $user->stats()->incrementMisc(UserSummaryMisc::forumThread);
        $forum->flush();
        return $thread;
    }

    /**
     * Instantiate a thread by its ID
     */
    public function findById(int $id): ?\Gazelle\ForumThread {
        $key = sprintf(self::ID_KEY, $id);
        $threadId = self::$cache->get_value($key);
        if ($threadId === false) {
            $threadId = (int)self::$db->scalar("
                SELECT ID FROM forums_topics WHERE ID = ?
                ", $id
            );
            if ($threadId) {
                self::$cache->cache_value($key, $threadId, 7200);
            }
        }
        return $threadId ? new \Gazelle\ForumThread($threadId) : null;
    }

    /**
     * Find the thread from a post ID.
     */
    public function findByPostId(int $postId): ?\Gazelle\ForumThread {
        $threadId = (int)self::$db->scalar("
            SELECT TopicID FROM forums_posts WHERE ID = ?
            ", $postId
        );
        return $threadId ? new \Gazelle\ForumThread($threadId) : null;
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
                $thread->addThreadNote(null, 'Locked automatically by schedule');
                $thread->flush();
                $thread->forum()->flush();
            }
        }
        return count($ids);
    }
}
