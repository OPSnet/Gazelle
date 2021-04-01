<?php

namespace Gazelle\Stats;

class Request extends \Gazelle\Base {
    protected $requestCount  = false;
    protected $filledCount   = false;
    protected $filledPercent = false;

    public function __construct() {
        parent::__construct();
        $info = $this->cache->get_value('stats_requests');
        if ($info !== false) {
            [$this->requestCount, $this->filledCount] = $info;
        } else {
            [$this->requestCount, $this->filledCount] = $this->db->row("
                SELECT count(*), sum(FillerID > 0) FROM requests
            ");
            $this->cache->cache_value('stats_requests', [$this->requestCount, $this->filledCount], 3600 * 3 + rand(0, 1800)); // three hours plus fuzz
        }
        $this->filledPercent = $this->requestCount > 0 ? $this->filledCount / $this->requestCount * 100 : 0.0;
    }

    public function requestCount(): int {
        return $this->requestCount;
    }

    public function filledCount(): int {
        return $this->filledCount;
    }

    public function filledPercent(): float {
        return $this->filledPercent;
    }
}
