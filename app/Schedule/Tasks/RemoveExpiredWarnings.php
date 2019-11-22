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
              AND Warned != '0000-00-00 00:00:00'");

        $this->db->prepared_query("
            UPDATE users_info
            SET Warned = '0000-00-00 00:00:00'
            WHERE Warned < now()");

        $this->db->set_query_id($queryId);
        while (list($userID) = $this->db->next_record()) {
            $this->cache->begin_transaction("user_info_$userID");
            $this->cache->update_row(false, ['Warned' => '0000-00-00 00:00:00']);
            $this->cache->commit_transaction(2592000);

            $this->debug("Expiring warning for $userID", $userID);
            $this->processed++;
        }

    }
}
