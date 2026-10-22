<?php

declare(strict_types=1);

namespace Gazelle\Manager;

class ForumPoll extends \Gazelle\BaseManager {
    final public const CACHE_FEATURED_POLL = 'polls_featured';
    final protected const ID_KEY = 'zz_fpoll_%d';

    /**
     * Create a poll for forum thread
     */
    # [\NoDiscard]
    public function create(
        \Gazelle\ForumThread $thread,
        string               $question,
        array                $answerList,
    ): \Gazelle\ForumPoll {
        self::$db->prepared_query("
            INSERT INTO forums_polls
                   (TopicID, Question, Answers)
            Values (?,       ?,        ?)
            ", $thread->id, $question, serialize($answerList)
        );
        $this->pg()->query('
            insert into forum_poll
                   (id_forum_thread, question, answer)
            values ($1,              $2,       $3)
            ', $thread->id, $question, json_encode($answerList)
        );
        return $this->findById($thread->id);
    }

    /**
     * Instantiate a poll by its thread ID
     */
    public function findById(int $id): ?\Gazelle\ForumPoll {
        $key = sprintf(self::ID_KEY, $id);
        $threadId = self::$cache->get_value($key);
        if ($threadId === false) {
            $threadId = (int)self::$db->scalar("
                SELECT TopicID FROM forums_polls WHERE TopicID = ?
                ", $id
            );
            if ($threadId) {
                self::$cache->cache_value($key, $threadId, 7200);
            }
        }
        return $threadId
            ? new \Gazelle\ForumPoll(new \Gazelle\ForumThread($threadId))
            : null;
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
