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
            $info = $this->pg()->rowAssocex("
                select count(*) AS total,
                    coalesce(sum(case when filled is null then 0 else 1 end), 0)::int as filled
                from request
            ");
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
