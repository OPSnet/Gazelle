<?php

declare(strict_types=1);

namespace Gazelle\Manager;

class ForumPoll extends \Gazelle\BaseManager {
    final public const CACHE_FEATURED_POLL = 'polls_featured';
    final protected const ID_KEY = 'zz_fpoll_%d';

    public function flush(): static {
        self::$cache->delete_value(self::CACHE_FEATURED_POLL);
        return $this;
    }

    /**
     * Create a poll for forum thread
     */
    # [\NoDiscard]
    public function create(
        \Gazelle\ForumThread $thread,
        string               $question,
        array                $answerList,
    ): \Gazelle\ForumPoll {
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
            $threadId = $this->pg()->scalarex('
                select id_forum_thread from forum_poll where id_forum_thread = $1
                ', $id
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
            $threadId = $this->pg()->scalarex('
                select id_forum_thread
                from forum_poll
                where is_featured
                    and not is_closed
                order by created desc
                limit 1
            ');
            self::$cache->cache_value(self::CACHE_FEATURED_POLL, $threadId, 86400 * 7);
        }
        return $this->findById((int)$threadId);
    }
}
