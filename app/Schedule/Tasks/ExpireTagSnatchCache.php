<?php

namespace Gazelle\Schedule\Tasks;

class ExpireTagSnatchCache extends \Gazelle\Schedule\Task
{
    public function run()
    {
        self::$db->prepared_query("
            SELECT DISTINCT uid
            FROM xbt_snatched
            WHERE tstamp > unix_timestamp(now() - INTERVAL 90 MINUTE)
        ");
        return count(self::$cache->deleteMulti(self::$db->collect('uid', false)));
    }
}
