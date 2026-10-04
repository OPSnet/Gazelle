<?php

namespace Gazelle\User;

use Gazelle\Manager\TGroup as TGroupManager;
use Gazelle\TGroup;
use Gazelle\TorrentAbstract;
use Gazelle\Util\CacheVector;

/**
 * A class to cache lookups of user snatch activity. Every snatch that a user
 * has made is mapped to a bit in bit vector. A series of fixed-length bit
 * vectors are created to cover the entire space from 1 to max(torrent_id).
 *
 * Vectors are lazily-loaded based on the torrent id given, and are cached for
 * a reasonable amount of time (less than an hour since the xbt_snatched table
 * is updated regularly).
 */

class Snatch extends \Gazelle\BaseUser {
    final public const tableName          = 'xbt_snatched';

    // A power-of-2 size, to be balanced against how many rows a query on xbt_snatched could return
    final protected const RANGE     = 17;
    final protected const RANGE_BIT = 2 ** self::RANGE;

    // base the cache name of the size strategy
    final protected const CACHE_KEY          = 'u_snatch_' . self::RANGE . '_%d_%d';
    final protected const CACHE_EXPIRY       = 2700;
    final protected const USER_RECENT_SNATCH = 'u_recent_snatch_%d';

    protected array $snatchVec = [];

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::USER_RECENT_SNATCH, $this->user->id));
        foreach ($this->snatchVec as $vector) {
            $vector->flush();
        }
        $this->snatchVec = [];
        return $this;
    }

    public function load(int $offset, CacheVector $vector): int {
        self::$db->prepared_query("
            SELECT DISTINCT fid
            FROM xbt_snatched
            WHERE uid = ?
                AND fid BETWEEN ? AND ?
            ", $this->user->id, $offset * self::RANGE_BIT, ($offset + 1) * self::RANGE_BIT - 1
        );
        return $vector->init($offset * self::RANGE_BIT, self::$db->collect(0));
    }

    protected function isTorrentIdSnatched(int $torrentId): bool {
        $offset = (int)floor($torrentId / self::RANGE_BIT);
        if (!isset($this->snatchVec[$offset])) {
            $vector = new CacheVector(sprintf(self::CACHE_KEY, $this->user->id, $offset), self::RANGE_BIT / 8, self::CACHE_EXPIRY);
            if ($vector->isEmpty()) {
                // the vector contents may have been cached, but if not, only we know how to initialize it
                $this->load($offset, $vector);
            }
            $this->snatchVec[$offset] = $vector;
        }
        return $this->snatchVec[$offset]->get($torrentId - $offset * self::RANGE_BIT);
    }

    public function isSnatched(TorrentAbstract $torrent): bool {
        if (!$this->user->option('ShowSnatched')) {
            return false;
        }
        return $this->isTorrentIdSnatched($torrent->id);
    }

    public function isTGroupSnatched(TGroup $tgroup): bool {
        if (!$this->user->option('ShowSnatched')) {
            return false;
        }
        foreach ($tgroup->torrentIdList() as $torrentId) {
            if ($this->isTorrentIdSnatched($torrentId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Default list 5 will be cached. When fetching a different amount, set
     * $flush to true to bust the cache.
     * This technique should be revisited, possibly by adding the limit to the key.
     */
    public function recentSnatchList(
        int  $limit = 5,
        bool $flush = false,
    ): array {
        $key = sprintf(self::USER_RECENT_SNATCH, $this->user->id);
        $recent = self::$cache->get_value($key);
        if ($flush) {
            $recent = false;
        }
        if ($recent === false) {
            /* A GROUP BY clause is added to the query, which has the effect of
             * deduplicating sequential snatches from the same group, so that
             * <n> distinct items are shown. For users that have a large number
             * of snatches, the cost of the query increases considerably: at
             * one million snatches the query takes about three seconds to
             * complete.
             * Therefore, once a user crosses a threshold, the GROUP BY is
             * removed, which means that two close recent snatches of the
             * same group will be shown on their profile. In theory this is
             * probably quite unlikely. To counteract such a result from
             * lingering too long, the cache interval has been reduced.
             */
            $groupBy = $this->user->stats()->snatchTotal() > FAST_LATEST_SNATCH_THRESHOLD
                ? '/* no group by */'
                : 'GROUP BY g.ID';
            self::$db->prepared_query("
                SELECT g.ID
                FROM xbt_snatched AS s
                INNER JOIN torrents AS t ON (t.ID = s.fid)
                INNER JOIN torrents_group AS g ON (t.GroupID = g.ID)
                WHERE g.CategoryID = 1
                    AND g.WikiImage != ''
                    AND t.UserID != s.uid
                    AND s.uid = ?
                $groupBy
                ORDER BY s.tstamp DESC
                LIMIT ?
                ", $this->user->id, $limit
            );
            $recent = self::$db->collect(0);
            self::$cache->cache_value($key, $recent, 3600);
        }
        return $recent;
    }
}
