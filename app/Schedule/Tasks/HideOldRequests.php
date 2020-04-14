<?php

namespace Gazelle\Schedule\Tasks;

class HideOldRequests extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
            UPDATE requests
            SET Visible = 0
            WHERE TimeFilled < (now() - INTERVAL 7 DAY)
                AND TimeFilled != '0000-00-00 00:00:00'"
        );
        $this->processed = $this->db->affected_rows();
    }
}
