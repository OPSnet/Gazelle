<?php

namespace Gazelle\Schedule\Tasks;

class RemoveDeadSessions extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $sessionQuery = $this->db->prepared_query("
            SELECT UserID, SessionID
            FROM users_sessions
            WHERE (LastUpdate < (now() - INTERVAL 30 DAY) AND KeepLogged = '1')
               OR (LastUpdate < (now() - INTERVAL 60 MINUTE) AND KeepLogged = '0')
        ");
        $this->db->prepared_query("
            DELETE FROM users_sessions
            WHERE (LastUpdate < (now() - INTERVAL 30 DAY) AND KeepLogged = '1')
               OR (LastUpdate < (now() - INTERVAL 60 MINUTE) AND KeepLogged = '0')
        ");

        $this->db->set_query_id($sessionQuery);
        while (list($userID, $sessionID) = $this->db->next_record()) {
            $this->cache->begin_transaction("users_sessions_$userID");
            $this->cache->delete_row($sessionID);
            $this->cache->commit_transaction(0);

            $this->processed++;
        }
    }
}
