<?php

namespace Gazelle\Schedule\Tasks;

class RemoveExpiredWarnings extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $queryId = $this->db->prepared_query("
            SELECT UserID
            FROM users_info
            WHERE Warned < now()
        ");

        $this->db->prepared_query("
            UPDATE users_info SET
                Warned = NULL
            WHERE Warned < now()
        ");

        $this->db->set_query_id($queryId);
        while (list($userID) = $this->db->next_record()) {
            $this->cache->delete_value("u_$userID");
            $this->debug("Expiring warning for $userID", $userID);
            $this->processed++;
        }
    }
}
