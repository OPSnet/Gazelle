<?php

namespace Gazelle\Schedule\Tasks;

class ExpireTagSnatchCache extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
            SELECT DISTINCT uid
            FROM xbt_snatched
            WHERE tstamp > unix_timestamp(now() - INTERVAL 90 MINUTE)
        ");
        return count($this->cache->deleteMulti($this->db->collect('uid', false)));
    }
}
