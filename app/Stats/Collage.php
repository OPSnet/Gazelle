<?php

namespace Gazelle\Stats;

class Collage extends \Gazelle\Base {
    public function collageCount(): int {
        if (($count = self::$cache->get_value('stats_collages')) === false) {
            $count = self::$db->scalar("
                SELECT count(*) FROM collages WHERE Deleted = '0'
            ");
            self::$cache->cache_value('stats_collages', $count, 43200 + rand(0, 300));
        }
        return $count;
    }
}
