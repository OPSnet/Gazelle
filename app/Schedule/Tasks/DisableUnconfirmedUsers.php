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
            WHERE um.LastAccess = '0000-00-00 00:00:00'
                AND ui.JoinDate < now() - INTERVAL 7 DAY
                AND um.Enabled != '2'");
        $userIDs = $this->db->collect('UserID');

        // disable the users
        $this->db->query("
            UPDATE users_info AS ui
            INNER JOIN users_main AS um ON (um.ID = ui.UserID)
            SET um.Enabled = '2',
                ui.BanDate = now(),
                ui.BanReason = '3',
                ui.AdminComment = CONCAT(now(), ' - Disabled for inactivity (never logged in)\n\n', ui.AdminComment)
            WHERE um.LastAccess = '0000-00-00 00:00:00'
                AND ui.JoinDate < now() - INTERVAL 7 DAY
                AND um.Enabled != '2'");
        if ($this->db->has_results()) {
            \Users::flush_enabled_users_count();
        }

        // clear the appropriate cache keys
        foreach ($userIDs as $userID) {
            $cache->delete_value("user_info_$userID");
            $this->processed++;
            $this->debug("Disabled $userID", $userID);
        }
    }
}
