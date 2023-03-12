<?php

namespace Gazelle\Schedule\Tasks;

class RatioWatch extends \Gazelle\Schedule\Task {
    public function run(): void {
        $userMan = new \Gazelle\Manager\User;

        // Take users off ratio watch and enable leeching
        $userQuery = self::$db->prepared_query("
            SELECT
                um.ID,
                um.torrent_pass
            FROM users_info AS i
            INNER JOIN users_main AS um ON (um.ID = i.UserID)
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            WHERE uls.Downloaded > 0
                AND uls.Uploaded / uls.Downloaded >= um.RequiredRatio
                AND i.RatioWatchEnds IS NOT NULL
                AND um.Enabled = '1'
        ");

        $offRatioWatch = self::$db->collect('ID');
        if (count($offRatioWatch) > 0) {
            self::$db->prepared_query("
                UPDATE users_info AS ui
                INNER JOIN users_main AS um ON (um.ID = ui.UserID) SET
                    ui.RatioWatchEnds     = NULL,
                    ui.RatioWatchDownload = '0',
                    um.can_leech          = '1',
                    ui.AdminComment       = concat(now(), ' - Taken off ratio watch by adequate ratio.\n\n', ui.AdminComment)
                WHERE ui.UserID IN (" . placeholders($offRatioWatch) . ")
            ", ...$offRatioWatch);

            foreach ($offRatioWatch as $userID) {
                self::$cache->delete_value("u_$userID");
                $userMan->sendPM($userID, 0,
                    'You have been taken off Ratio Watch',
                    "Congratulations! Feel free to begin downloading again.\n To ensure that you do not get put on ratio watch again, please read the rules located [url=rules.php?p=ratio]here[/url].\n"
                );

                $this->processed++;
                $this->debug("Taking $userID off ratio watch", $userID);
            }

            self::$db->set_query_id($userQuery);
            $passkeys = self::$db->collect('torrent_pass');
            $tracker = new \Gazelle\Tracker;
            foreach ($passkeys as $passkey) {
                $tracker->update_tracker('update_user', ['passkey' => $passkey, 'can_leech' => '1']);
            }
        }

        // Put users on ratio watch if they don't meet the standards
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_info AS i
            INNER JOIN users_main AS um ON (um.ID = i.UserID)
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            WHERE uls.Downloaded > 0
                AND uls.Uploaded / uls.Downloaded < um.RequiredRatio
                AND i.RatioWatchEnds IS NULL
                AND um.Enabled = '1'
                AND um.can_leech = '1'
        ");

        $onRatioWatch = self::$db->collect('ID');
        if (count($onRatioWatch) > 0) {
            self::$db->prepared_query("
                UPDATE users_info AS i
                INNER JOIN users_main AS um ON (um.ID = i.UserID)
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID) SET
                    i.RatioWatchEnds     = now() + INTERVAL 2 WEEK,
                    i.RatioWatchTimes    = i.RatioWatchTimes + 1,
                    i.RatioWatchDownload = uls.Downloaded
                WHERE um.ID IN (" . placeholders($onRatioWatch) . ")
            ", ...$onRatioWatch);

            foreach ($onRatioWatch as $userID) {
                self::$cache->delete_value("u_$userID");
                $userMan->sendPM($userID, 0,
                    'You have been put on Ratio Watch',
                    "This happens when your ratio falls below the requirements outlined in the rules located [url=rules.php?p=ratio]here[/url].\n For information about ratio watch, click the link above."
                );
                $this->processed++;
                $this->debug("Putting $userID on ratio watch", $userID);
            }
        }
    }
}
