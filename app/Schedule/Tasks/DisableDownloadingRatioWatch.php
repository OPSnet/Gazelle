<?php

namespace Gazelle\Schedule\Tasks;

class DisableDownloadingRatioWatch extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $userQuery = $this->db->prepared_query("
            SELECT ID, torrent_pass
            FROM users_info AS i
            INNER JOIN users_main AS m ON (m.ID = i.UserID)
            WHERE i.RatioWatchEnds < now()
                AND m.Enabled = '1'
                AND m.can_leech != '0'");

        $userIDs = $this->db->collect('ID');
        if (count($userIDs) > 0) {
            $placeholders = placeholders($userIDs);
            $this->db->prepared_query("
                UPDATE users_info AS i
                INNER JOIN users_main AS m ON (m.ID = i.UserID)
                SET m.can_leech = '0',
                    i.AdminComment = CONCAT(now(), ' - Leeching ability disabled by ratio watch system - required ratio: ', m.RequiredRatio, '\n\n', i.AdminComment)
                WHERE m.ID IN($placeholders)
            ", ...$userIDs);

            $this->db->prepared_query("
                DELETE FROM users_torrent_history
                WHERE UserID IN ($placeholders)
            ", $userIDs);
        }

        $userMan = new \Gazelle\Manager\User;
        foreach ($userIDs as $userID) {
            $this->cache->deleteMulti(["u_$userID", "user_info_heavy_$userID"]);
            $userMan->sendPM($userID, 0,
                'Your downloading privileges have been disabled',
                "As you did not raise your ratio in time, your downloading privileges have been revoked. You will not be able to download any torrents until your ratio is above your new required ratio."
            );
            $this->debug("Disabled leech for $userID", $userID);
            $this->processed++;
        }

        $this->db->set_query_id($userQuery);
        $passkeys = $this->db->collect('torrent_pass');
        $tracker = new \Gazelle\Tracker;
        foreach ($passkeys as $passkey) {
            $tracker->update_tracker('update_user', ['passkey' => $passkey, 'can_leech' => '0']);
        }
    }
}
