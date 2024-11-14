<?php

namespace Gazelle\Stats;

class Collage extends \Gazelle\Base {
    protected const CACHE_KEY = 'stats_collages';

    public function collageTotal(): int {
        $count = self::$cache->get_value(self::CACHE_KEY);
        if ($count === false) {
            $count = (int)self::$db->scalar("
                SELECT count(*) FROM collages WHERE Deleted = '0'
            ");
            self::$cache->cache_value(self::CACHE_KEY, $count, 43200 + random_int(0, 300));
        }
        return $count;
    }

    public function increment(): int {
        $count = (int)self::$cache->get_value(self::CACHE_KEY) + 1;
        self::$cache->cache_value(self::CACHE_KEY, $count, 43200 + random_int(0, 300));
        return $count;
    }
}
