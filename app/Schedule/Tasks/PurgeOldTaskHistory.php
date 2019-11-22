<?php

namespace Gazelle\Schedule\Tasks;

class PurgeOldTaskHistory extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query('
            DELETE FROM periodic_task_history
            WHERE launch_time < now() - INTERVAL 6 MONTH
        ');
        $this->processed = $this->db->affected_rows();
    }
}
