<?php

namespace Gazelle\Stats;

class Request extends \Gazelle\Base {
    protected const CACHE_KEY = 'stats_req';

    protected array $info;

    public function flush(): static {
        self::$cache->delete_value(self::CACHE_KEY);
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $info = self::$cache->get_value(self::CACHE_KEY);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT count(*)                 AS total,
                    sum(if(FillerID > 0, 1, 0)) AS filled
                FROM requests
            ");
            $info['filled'] = (int)$info['filled'];
            self::$cache->cache_value(self::CACHE_KEY, $info, 3600 + random_int(0, 300)); // a bit over an hour
        }
        $this->info = $info;
        return $this->info;
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
