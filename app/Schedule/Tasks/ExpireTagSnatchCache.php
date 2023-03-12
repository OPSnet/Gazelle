<?php

namespace Gazelle\Schedule\Tasks;

class ExpireTagSnatchCache extends \Gazelle\Schedule\Task {
    public function run(): void {
        self::$db->prepared_query("
            SELECT DISTINCT uid
            FROM xbt_snatched
            WHERE tstamp > unix_timestamp(now() - INTERVAL 90 MINUTE)
        ");
        $this->processed =  count(self::$cache->delete_multi(self::$db->collect('uid', false)));
    }
}
