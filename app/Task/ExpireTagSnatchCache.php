<?php

namespace Gazelle\Task;

class ExpireTagSnatchCache extends \Gazelle\Task {
    public function run(): void {
        self::$db->prepared_query("
            SELECT DISTINCT uid
            FROM xbt_snatched
            WHERE tstamp > unix_timestamp(now() - INTERVAL 90 MINUTE)
        ");
        $this->processed = count(self::$cache->delete_multi(self::$db->collect(0, false)));
    }
}
