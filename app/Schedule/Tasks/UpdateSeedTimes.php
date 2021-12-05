<?php

namespace Gazelle\Schedule\Tasks;

class UpdateSeedTimes extends \Gazelle\Schedule\Task
{
    public function run()
    {
        self::$db->prepared_query('
            INSERT INTO xbt_files_history (uid, fid, seedtime)
                SELECT DISTINCT uid, fid, 1
                FROM xbt_files_users
                WHERE active = 1 AND remaining = 0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR)
            ON DUPLICATE KEY UPDATE seedtime = seedtime + 1
        ');
    }
}
