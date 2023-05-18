<?php

namespace Gazelle\Task;

class PurgeOldTaskHistory extends \Gazelle\Task {
    public function run(): void {
        self::$db->prepared_query('
            DELETE FROM periodic_task_history
            WHERE launch_time < now() - INTERVAL 6 MONTH
        ');
        $this->processed = self::$db->affected_rows();
    }
}
