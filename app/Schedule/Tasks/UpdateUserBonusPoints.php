<?php

namespace Gazelle\Schedule\Tasks;

class UpdateUserBonusPoints extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $Bonus = new \Gazelle\Bonus($DB, $Cache);
        $Bonus->givePoints();
        $this->db->prepared_query("SELECT UserID FROM users_info WHERE DisablePoints = '0'");
        if ($this->db->has_results()) {
            while(list($userID) = $this->db->next_record()) {
                $this->cache->delete_value('user_stats_'.$userID);
                $this->processed++;
            }
        }
    }
}
