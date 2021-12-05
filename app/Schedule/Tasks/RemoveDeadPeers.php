<?php

namespace Gazelle\Schedule\Tasks;

class RemoveDeadPeers extends \Gazelle\Schedule\Task
{
    public function run()
    {
        self::$db->prepared_query("
            DELETE FROM xbt_files_users
            WHERE mtime < unix_timestamp(NOW() - INTERVAL 6 HOUR)
        ");
        $this->processed = self::$db->affected_rows();
    }
}
