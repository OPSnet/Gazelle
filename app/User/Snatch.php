<?php

namespace Gazelle\User;

use \Gazelle\Util\CacheVector;

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

    // A power-of-2 size, to be balanced against how many rows a query on xbt_snatched could return
    const RANGE     = 17;
    const RANGE_BIT = 2 ** self::RANGE;

    // base the cache name of the size strategy
    const CACHE_KEY    = 'u_snatch_' . self::RANGE . '_%d_%d';
    const CACHE_EXPIRY = 2700;

    protected array $snatchVec = [];

    public function flush(): Snatch {
        foreach (array_values($this->snatchVec) as $vector) {
            $vector->flush();
        }
        return $this;
    }

    public function isSnatched(int $torrentId): bool {
        $offset = (int)floor($torrentId / self::RANGE_BIT);
        if (!isset($this->snatchVec[$offset])) {
            $vector = new CacheVector(sprintf(self::CACHE_KEY, $this->user->id(), $offset), self::RANGE_BIT / 8, self::CACHE_EXPIRY);
            $total = -1;
            if ($vector->isEmpty()) {
                // the vector contents might have been cached, but if not, only we know how to initialize it
                $this->load($offset, $vector);
            }
            $this->snatchVec[$offset] = $vector;
        }
        return $this->snatchVec[$offset]->get($torrentId - $offset * self::RANGE_BIT);
    }

    public function showSnatch(int $torrentId): bool {
        return (bool)$this->user->option('ShowSnatched') && $this->isSnatched($torrentId);
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
}
