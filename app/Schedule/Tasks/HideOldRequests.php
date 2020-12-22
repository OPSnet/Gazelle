<?php

namespace Gazelle\Schedule\Tasks;

class HideOldRequests extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
            UPDATE requests SET
                Visible = 0
            WHERE TimeFilled < (now() - INTERVAL 7 DAY)
        ");
        $this->processed = $this->db->affected_rows();
    }
}
