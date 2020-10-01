<?php

namespace Gazelle\Stats;

class Collage extends \Gazelle\Base {
    public function collageCount(): int {
        if (($count = $this->cache->get_value('stats_collages')) === false) {
            $count = $this->db->scalar("
                SELECT count(*) FROM collages WHERE Deleted = '0'
            ");
            $this->cache->cache_value('stats_collages', $count, 43200 + rand(0, 300));
        }
        return $count;
    }
}
