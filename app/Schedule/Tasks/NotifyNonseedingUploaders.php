<?php

namespace Gazelle\Schedule\Tasks;

class NotifyNonseedingUploaders extends \Gazelle\Schedule\Task
{
    public function run()
    {
        // Send warnings to uploaders of torrents that will be deleted this week
        self::$db->prepared_query("
            SELECT
                t.ID,
                t.GroupID,
                tg.Name,
                t.Format,
                t.Encoding,
                t.UserID
            FROM torrents AS t
                INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                INNER JOIN users_info AS u ON (u.UserID = t.UserID)
            WHERE tls.last_action < NOW() - INTERVAL 20 DAY
                AND tls.last_action != 0
                AND u.UnseededAlerts = '1'
            ORDER BY tls.last_action ASC"
        );

        $torrentIDs = self::$db->to_array();
        $torrentAlerts = [];
        $inactivityExceptionsMade = [];

        foreach ($torrentIDs as $torrentID) {
            list($id, $groupID, $name, $format, $encoding, $userID) = $torrentID;

            if (array_key_exists($userID, $inactivityExceptionsMade) && (time() < $inactivityExceptionsMade[$userID])) {
                // don't notify exceptions
                continue;
            }

            if (!array_key_exists($userID, $torrentAlerts)) {
                $torrentAlerts[$userID] = ['Count' => 0, 'Msg' => ''];
            }

            $artistName = \Artists::display_artists(\Artists::get_artist($groupID), false, false, false);
            if ($artistName) {
                $name = "$artistName - $name";
            }

            if ($format && $encoding) {
                $name .= " [$format / $encoding]";
            }

            $torrentAlerts[$userID]['Msg'] .= "\n[url=torrents.php?torrentid=$id]".$name."[/url]";
            $torrentAlerts[$userID]['Count']++;

            $this->processed++;
        }

        $userMan = new \Gazelle\Manager\User;
        foreach ($torrentAlerts as $userID => $messageInfo) {
            $userMan->sendPM($userID, 0,
                'Unseeded torrent notification',
                $messageInfo['Count'] . " of your uploads will be deleted for inactivity soon. Unseeded torrents are deleted after 4 weeks. If you still have the files, you can seed your uploads by ensuring the torrents are in your client and that they aren't stopped. You can view the time that a torrent has been unseeded by clicking on the torrent description line and looking for the \"Last active\" time. For more information, please go [url=wiki.php?action=article&amp;id=77]here[/url].\n\nThe following torrent".plural($messageInfo['Count']).' will be removed for inactivity:'.$messageInfo['Msg']."\n\nIf you no longer wish to receive these notifications, please disable them in your profile settings."
            );
            $this->debug("Warning user $userID about ${messageInfo['Count']} torrents", $userID);
        }
    }
}
