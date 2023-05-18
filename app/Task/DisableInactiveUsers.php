<?php

namespace Gazelle\Task;

use Gazelle\Util\Mail;

class DisableInactiveUsers extends \Gazelle\Task
{
    protected function userQuery($minDays, $maxDays): void {
        self::$db->prepared_query("
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

    public function run(): void {
        // Send email
        $this->userQuery(110, 111);
        $mail = new Mail;
        $twig = \Gazelle\Util\Twig::factory();
        while ([$username, $email] = self::$db->next_record()) {
            $mail->send($email, 'Your ' . SITE_NAME . ' account is about to be deactivated',
                $twig->render('email/disable-warning.twig', [
                    'username' => $username,
                ])
            );
        }

        $this->userQuery(120, 180);
        if (self::$db->has_results()) {
            $userIDs = self::$db->collect('ID');
            $userMan = new \Gazelle\Manager\User;
            $userMan->disableUserList(new \Gazelle\Tracker, $userIDs, 'Disabled for inactivity.', \Gazelle\Manager\User::DISABLE_INACTIVITY);
            foreach ($userIDs as $userID) {
                $this->processed++;
                $this->debug("Disabling $userID", $userID);
            }
            $userMan->flushEnabledUsersCount();
        }
    }
}
