<?php

namespace Gazelle\User;

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
        self::$cache->delete_value(sprintf(self::USER_RECENT_SNATCH, $this->id()));
        foreach (array_values($this->snatchVec) as $vector) {
            $vector->flush();
        }
        return $this;
    }

    public function load(int $offset, CacheVector $vector): int {
        self::$db->prepared_query("
            SELECT DISTINCT fid
            FROM xbt_snatched
            WHERE uid = ?
                AND fid BETWEEN ? AND ?
            ", $this->user->id(), $offset * self::RANGE_BIT, ($offset + 1) * self::RANGE_BIT - 1
        );
        return $vector->init($offset * self::RANGE_BIT, self::$db->collect(0, false));
    }

    public function isSnatched(\Gazelle\TorrentAbstract $torrent): bool {
        $offset = (int)floor($torrent->id() / self::RANGE_BIT);
        if (!isset($this->snatchVec[$offset])) {
            $vector = new CacheVector(sprintf(self::CACHE_KEY, $this->user->id(), $offset), self::RANGE_BIT / 8, self::CACHE_EXPIRY);
            if ($vector->isEmpty()) {
                // the vector contents might have been cached, but if not, only we know how to initialize it
                $this->load($offset, $vector);
            }
            $this->snatchVec[$offset] = $vector;
        }
        return $this->snatchVec[$offset]->get($torrent->id() - $offset * self::RANGE_BIT);
    }

    public function showSnatch(\Gazelle\TorrentAbstract $torrent): bool {
        return (bool)$this->user->option('ShowSnatched') && $this->isSnatched($torrent);
    }

    /**
     * Default list 5 will be cached. When fetching a different amount,
     * set $forceNoCache to true to avoid caching a list with an unexpected length.
     * This technique should be revisited, possibly by adding the limit to the key.
     */
    public function recentSnatchList(int $limit = 5, bool $forceNoCache = false): array {
        $key = sprintf(self::USER_RECENT_SNATCH, $this->id());
        $recent = self::$cache->get_value($key);
        if ($forceNoCache) {
            $recent = false;
        }
        if ($recent === false) {
            self::$db->prepared_query("
                SELECT g.ID
                FROM xbt_snatched AS s
                INNER JOIN torrents AS t ON (t.ID = s.fid)
                INNER JOIN torrents_group AS g ON (t.GroupID = g.ID)
                WHERE g.CategoryID = '1'
                    AND g.WikiImage != ''
                    AND t.UserID != s.uid
                    AND s.uid = ?
                GROUP BY g.ID
                ORDER BY s.tstamp DESC
                LIMIT ?
                ", $this->id(), $limit
            );
            $recent = self::$db->collect(0, false);
            if (!$forceNoCache) {
                self::$cache->cache_value($key, $recent, 86400 * 3);
            }
        }
        return $recent;
    }
}
