<?php

namespace Gazelle\Schedule\Tasks;

class DisableUnconfirmedUsers extends \Gazelle\Schedule\Task
{
    public function run()
    {
        // get a list of user IDs for clearing cache keys
        $this->db->prepared_query("
            SELECT UserID
            FROM users_info AS ui
            INNER JOIN users_main AS um ON (um.ID = ui.UserID)
            LEFT JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            WHERE ula.user_id IS NULL
                AND ui.JoinDate < now() - INTERVAL 7 DAY
                AND um.Enabled != '2'
            "
        );
        $userIDs = $this->db->collect('UserID');

        // disable the users
        $this->db->prepared_query("
            UPDATE users_info AS ui
            INNER JOIN users_main AS um ON (um.ID = ui.UserID)
            LEFT JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            SET um.Enabled = '2',
                ui.BanDate = now(),
                ui.BanReason = '3',
                ui.AdminComment = CONCAT(now(), ' - Disabled for inactivity (never logged in)\n\n', ui.AdminComment)
            WHERE ula.user_id IS NULL
                AND ui.JoinDate < now() - INTERVAL 7 DAY
                AND um.Enabled != '2'
            "
        );
        if ($this->db->has_results()) {
            $userMan = new \Gazelle\Manager\User;
            $userMan->flushEnabledUsersCount();
        }

        // clear the appropriate cache keys
        foreach ($userIDs as $userID) {
            $this->cache->delete_value("u_$userID");
            $this->processed++;
            $this->debug("Disabled $userID", $userID);
        }
    }
}
