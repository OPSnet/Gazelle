<?php

namespace Gazelle\Manager;

class ForumPoll extends \Gazelle\BaseManager {
    final const CACHE_FEATURED_POLL = 'polls_featured';
    protected const ID_KEY = 'zz_fpoll_%d';

    /**
     * Create a poll for forum thread
     */
    public function create(int $threadId, string $question, array $answerList): \Gazelle\ForumPoll {
        self::$db->prepared_query("
            INSERT INTO forums_polls
                   (TopicID, Question, Answers)
            Values (?,       ?,        ?)
            ", $threadId, $question, serialize($answerList)
        );
        return $this->findById($threadId);
    }

    /**
     * Instantiate a poll by its thread ID
     */
    public function findById(int $threadId): ?\Gazelle\ForumPoll {
        $key = sprintf(self::ID_KEY, $threadId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = (int)self::$db->scalar("
                SELECT TopicID FROM forums_polls WHERE TopicID = ?
                ", $threadId
            );
            if ($id) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\ForumPoll($id) : null;
    }

    /**
     * Find the poll featured on the front page.
     */
    public function findByFeaturedPoll(): ?\Gazelle\ForumPoll {
        $threadId = self::$cache->get_value(self::CACHE_FEATURED_POLL);
        if ($threadId === false) {
            $threadId = (int)self::$db->scalar("
                SELECT TopicID
                FROM forums_polls
                WHERE Featured IS NOT NULL
                    AND Closed = '0'
                ORDER BY Featured DESC
                LIMIT 1
            ");
            self::$cache->cache_value(self::CACHE_FEATURED_POLL, $threadId, 86400 * 7);
        }
        return $this->findById((int)$threadId);
    }
}
