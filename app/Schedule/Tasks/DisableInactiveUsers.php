<?php

namespace Gazelle\Schedule\Tasks;

class DisableInactiveUsers extends \Gazelle\Schedule\Task
{
    protected function query($minDays, $maxDays) {
        $this->db->prepared_query("
            SELECT um.Username, um.Email, um.ID
            FROM users_main AS um
            INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            INNER JOIN permissions p ON (p.ID = um.PermissionID)
            WHERE um.Enabled != '2'
                AND ula.last_access BETWEEN date(now() - INTERVAL ? DAY) AND date(now() - INTERVAL ? DAY)
                AND NOT EXISTS (
                    SELECT 1
                    FROM users_levels ul
                    INNER JOIN permissions ulp ON (ulp.ID = ul.PermissionID)
                    WHERE ul.UserID = um.ID
                        AND ulp.Name in (?, ?)
                )
                AND p.Name IN (?, ?)
            GROUP BY um.ID
            ", $maxDays, $minDays,
            'Donor', 'Torrent Celebrity',
            'User', 'Member'
        );
    }

    public function run() {
        // Send email
        $this->query(110, 111);
        while (list($username, $email) = $this->db->next_record()) {
            $body = "Hi $username,\n\nIt has been almost 4 months since you used your account at ".site_url().". This is an automated email to inform you that your account will be disabled in 10 days if you do not sign in.";
            \Misc::send_email($email, 'Your '.SITE_NAME.' account is about to be disabled', $body, 'noreply');
        }

        $this->query(120, 180);
        if ($this->db->has_results()) {
            $userIDs = $this->db->collect('ID');
            \Tools::disable_users($userIDs, 'Disabled for inactivity.', 3);
            foreach ($userIDs as $userID) {
                $this->processed++;
                $this->debug("Disabling $userID", $userID);
            }
            \Users::flush_enabled_users_count();
        }
    }
}
