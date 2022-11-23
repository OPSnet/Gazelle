<?php

namespace Gazelle\Stats;

class Request extends \Gazelle\Base {
    protected const CACHE_KEY = 'stats_req';

    protected array $info;

    public function info() {
        $info = self::$cache->get_value(self::CACHE_KEY);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT count(*)                 AS total,
                    sum(if(FillerID > 0, 1, 0)) AS filled
                FROM requests
            ");
            self::$cache->cache_value(self::CACHE_KEY, $info, 3600 * 3 + rand(0, 1800)); // three hours plus fuzz
            $this->info = $info;
        }
        return $info;
    }

    public function total(): int {
        return $this->info()['total'];
    }

    public function filledTotal(): int {
        return $this->info()['filled'];
    }

    public function filledPercent(): float {
        return $this->total() > 0
            ? $this->filledTotal() / $this->total() * 100
            : 0.0;
    }
}
