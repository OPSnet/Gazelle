<?php

namespace Gazelle\Schedule\Tasks;

class RemoveExpiredWarnings extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $queryId = self::$db->prepared_query("
            SELECT UserID
            FROM users_info
            WHERE Warned < now()
        ");

        self::$db->prepared_query("
            UPDATE users_info SET
                Warned = NULL
            WHERE Warned < now()
        ");

        self::$db->set_query_id($queryId);
        while (list($userID) = self::$db->next_record()) {
            self::$cache->delete_value("u_$userID");
            $this->debug("Expiring warning for $userID", $userID);
            $this->processed++;
        }
    }
}
