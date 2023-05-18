<?php

namespace Gazelle\Task;

class RemoveExpiredWarnings extends \Gazelle\Task {
    public function run(): void {
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
        while ([$userID] = self::$db->next_record()) {
            self::$cache->delete_value("u_$userID");
            $this->debug("Expiring warning for $userID", $userID);
            $this->processed++;
        }
    }
}
