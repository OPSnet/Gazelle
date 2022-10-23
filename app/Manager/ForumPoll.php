<?php

namespace Gazelle\Manager;

class ForumPoll extends \Gazelle\BaseManager {

    protected const ID_KEY = 'zz_fpoll_%d';

    /**
     * Instantiate a poll by its thread ID
     */
    public function findById(int $threadId): ?\Gazelle\ForumPoll {
        $key = sprintf(self::ID_KEY, $threadId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT TopicID FROM forums_polls WHERE TopicID = ?
                ", $threadId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\ForumPoll($id) : null;
    }

    /**
     * Find the poll featured on the front page.
     */
    public function findByFeaturedPoll(): ?\Gazelle\ForumPoll {
        $pollId = self::$cache->get_value('polls_featured');
        if ($pollId === false) {
            $pollId = self::$db->scalar("
                SELECT TopicID
                FROM forums_polls
                WHERE Featured IS NOT NULL
                ORDER BY Featured DESC
                LIMIT 1
            ");
            self::$cache->cache_value('polls_featured', $pollId, 86400 * 7);
        }
        return $this->findById((int)$pollId);
    }

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
        return new \Gazelle\ForumPoll(self::$db->inserted_id());
    }
}
