<?php

namespace Gazelle\Schedule\Tasks;

class UpdateUserBonusPoints extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $bonus = new \Gazelle\Bonus($this->db, $this->cache);
        $bonus->givePoints();
        $this->db->prepared_query("SELECT UserID FROM users_info WHERE DisablePoints = '0'");
        if ($this->db->has_results()) {
            while(list($userID) = $this->db->next_record()) {
                $this->cache->delete_value('user_stats_'.$userID);
                $this->processed++;
            }
        }
    }
}
