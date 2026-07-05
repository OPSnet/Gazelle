<?php

namespace Gazelle\Manager;

use Gazelle\Forum;
use Gazelle\User;

class News extends \Gazelle\Base {
    final protected const CACHE_KEY = 'newsv2';

    /**
     * Create a news article. This class deviates from the usual architecture of
     * having a Manager\News and News implementation, because it is so simple.
     * Consequently, the manager takes care of updating and removing individual
     * news items.
     */
    public function create(
        string      $title,
        string      $body,
        string      $pitch,
        User        $user,
        Forum       $forum,
        ForumThread $threadMan = new ForumThread(),
    ): int {
        $body   = trim($body);
        $title  = trim($title);
        $pitch  = trim($pitch);
        $thread = $threadMan->create($forum, $user, $title, $body);

        $body .= "\n\n[url=/forums.php?action=viewthread&threadid={$thread->id}]{$pitch}[/url]";
        $id = $this->pg()->scalarex("
            insert into news
                   (id_user, id_thread, title, body)
            values ($1,      $2,        $3,    $4)
            returning id_news
            ", $user->id, $thread->id, $title, $body
        );
        self::$cache->delete_multi(['feed_news', self::CACHE_KEY]);
        return $id;
    }

    /**
     * Modify an existing news article (the author remains unchanged)
     */
    public function modify(int $id, string $title, string $body): int {
        $affected = $this->pg()->query("
            update news set
                title = $1,
                body  = $2
            where id_news = $3
            ", trim($title), trim($body), $id
        );
        self::$cache->delete_multi(['feed_news', self::CACHE_KEY]);
        return $affected;
    }

    public function list(int $limit, int $offset): array {
        return $this->pg()->allex("
            select id_news as id,
                title,
                body,
                created
            from news
            order by created desc
            limit $1 offset $2
            ", $limit, $offset
        );
    }

    /**
     * Get a number of most recent articles.
     * (hard-coded to 20 max, otherwise cache invalidation becomes difficult)
     *
     * @return array [id, title, body, creation date]
     */
    public function headlines(): array {
        $headlines = self::$cache->get_value(self::CACHE_KEY);
        if ($headlines === false) {
            $headlines = $this->list(20, 0);
            self::$cache->cache_value(self::CACHE_KEY, $headlines, 0);
        }
        return $headlines;
    }

    /**
     * Get the title and body of an article
     *
     * @return array [string title, string body] or null if no such article
     */
    public function fetch(int $id): ?array {
        $article = $this->pg()->rowex("
            select title, body
            from news
            where id_news = $1
            ", $id
        );
        return $article === [] ? null : $article;
    }

    /**
     * Get the latest news article id and title
     * ID will be -1 if no news yet exists.
     *
     * @return array [id, title]
     */
    public function latest(): array {
        $headlines = $this->headlines();
        return $headlines[0] ?? ["id" => -1, "title" => null, "body" => null, "created" => '2001-01-01 00:00:00'];
    }

    /**
     * Get the latest news article id
     * ID will be -1 if no news yet exists.
     *
     * @return int $id news article id
     */
    public function latestId(): int {
        return $this->latest()['id'];
    }

    /**
     * Get the epoch of the most recent entry
     */
    public function latestEpoch(): int {
        $latest = $this->headlines();
        return $latest ? $latest[0]['created']->format('U') : 0;
    }

    /**
     * Remove an existing news article
     */
    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM user_read_news WHERE news_id = ?
            ", $id
        );
        $affected = self::$db->affected_rows();
        $affected += $this->pg()->query("
            delete from news where id_news = $1
            ", $id
        );
        self::$cache->delete_multi(['feed_news', self::CACHE_KEY]);
        return $affected;
    }
}
