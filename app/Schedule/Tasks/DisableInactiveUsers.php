<?php

namespace Gazelle\Schedule\Tasks;

class DisableInactiveUsers extends \Gazelle\Schedule\Task
{
    public function run()
    {
        // Send email
        $this->db->prepared_query("
            SELECT um.Username, um.Email
            FROM users_info AS ui
            INNER JOIN users_main AS um ON (um.ID = ui.UserID)
            LEFT JOIN users_levels AS ul ON (ul.UserID = um.ID AND ul.PermissionID = ?)
            WHERE um.PermissionID IN (?, ?)
                AND um.LastAccess < date(now() - INTERVAL 110 DAY)
                AND um.LastAccess > date(now() - INTERVAL 111 DAY)
                AND um.LastAccess != '0000-00-00 00:00:00'
                AND ui.Donor = '0'
                AND um.Enabled != '2'
                AND ul.UserID IS NULL
            GROUP BY um.ID
        ", CELEB, USER, MEMBER);

        while (list($username, $email) = $this->db->next_record()) {
            $body = "Hi $username,\n\nIt has been almost 4 months since you used your account at ".site_url().". This is an automated email to inform you that your account will be disabled in 10 days if you do not sign in.";
            Misc::send_email($email, 'Your '.SITE_NAME.' account is about to be disabled', $body, 'noreply');
        }

        $this->db->prepared_query("
            SELECT um.ID
            FROM users_info AS ui
            INNER JOIN users_main AS um ON (um.ID = ui.UserID)
            LEFT JOIN users_levels AS ul ON (ul.UserID = um.ID AND ul.PermissionID = ?)
            WHERE um.PermissionID IN (?, ?)
                AND um.LastAccess < now() - INTERVAL 120 DAY
                AND um.LastAccess != '0000-00-00 00:00:00'
                AND ui.Donor = '0'
                AND um.Enabled != '2'
                AND ul.UserID IS NULL
            GROUP BY um.ID
        ", CELEB, USER, MEMBER);

        if ($this->db->has_results()) {
            $userIDs = $this->db->collect('ID');
            Tools::disable_users($userIDs, 'Disabled for inactivity.', 3);
            Users::flush_enabled_users_count();
            foreach ($userIDs as $userID) {
                $this->processed++;
                $this->debug("Disabling $userID", $userID);
            }
        }
    }
}
