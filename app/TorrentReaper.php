<?php

namespace Gazelle;

class TorrentReaper extends Base {

    public function deleteDeadTorrents(bool $unseeded, bool $neverSeeded) {
        if (!$unseeded && !$neverSeeded) {
            return [];
        }

        $criteria = [];
        if ($unseeded) {
            $criteria[] = '(tls.last_action IS NOT NULL AND tls.last_action < now() - INTERVAL 28 DAY)';
        }
        if ($neverSeeded) {
            $criteria[] = '(tls.last_action IS NULL AND t.Time < now() - INTERVAL 2 DAY)';
        }

        $criteria = implode(' OR ', $criteria);

        $this->db->prepared_query("
            SELECT
                t.ID,
                t.GroupID,
                tg.Name,
                t.Format,
                t.Encoding,
                t.UserID,
                t.Media,
                HEX(t.info_hash) AS InfoHash
            FROM torrents AS t
            INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
            INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
            WHERE $criteria
            LIMIT 8000
        ");
        $torrents = $this->db->to_array(0, MYSQLI_NUM, false);

        $logEntries = $deleteNotes = [];

        $i = 0;
        foreach ($torrents as $torrent) {
            list($id, $groupID, $name, $format, $encoding, $userID, $media, $infoHash) = $torrent;
            $artistName = \Artists::display_artists(\Artists::get_artist($groupID), false, false, false);
            if ($artistName) {
                $name = "$artistName - $name";
            }

            if ($format && $encoding) {
                $name .= ' ['.(empty($media) ? '' : "$media / ") . "$format / $encoding]";
            }

            \Torrents::delete_torrent($id, $groupID);
            $logEntries[] = "Torrent $id ($name) (".strtoupper($infoHash).") was deleted for inactivity (unseeded)";

            if (!array_key_exists($userID, $deleteNotes)) {
                $deleteNotes[$userID] = ['Count' => 0, 'Msg' => ''];
            }

            $deleteNotes[$userID]['Msg'] .= sprintf("\n[url=%storrents.php?id=%s]%s[/url]", site_url(), $groupID, $name);
            $deleteNotes[$userID]['Count']++;

            ++$i;
        }

        foreach ($deleteNotes as $userID => $messageInfo) {
            $singular = (($messageInfo['Count'] == 1) ? true : false);
            \Misc::send_pm($userID, 0, $messageInfo['Count'].' of your torrents '.($singular ? 'has' : 'have').' been deleted for inactivity', ($singular ? 'One' : 'Some').' of your uploads '.($singular ? 'has' : 'have').' been deleted for being unseeded. Since '.($singular ? 'it' : 'they').' didn\'t break any rules (we hope), please feel free to re-upload '.($singular ? 'it' : 'them').".\n\nThe following torrent".($singular ? ' was' : 's were').' deleted:'.$messageInfo['Msg']);
        }
        unset($deleteNotes);

        if (count($logEntries) > 0) {
            $chunks = array_chunk($logEntries, 100);
            foreach ($chunks as $messages) {
                $this->db->prepared_query("
                    INSERT INTO log (Message, Time)
                    VALUES " . placeholders($messages, '(?, now())')
                    , ...$messages
                );
            }
        }

        $this->db->prepared_query("
            SELECT SimilarID
            FROM artists_similar_scores
            WHERE Score <= 0");
        $similarIDs = $this->db->collect('SimilarID');

        if ($similarIDs) {
            $this->db->prepared_query("
                DELETE FROM artists_similar
                WHERE SimilarID IN (" . placeholders($similarIDs, '(?)') . ")
            ", ...$similarIDs);
            $placeholders = placeholders($similarIDs);
            $this->db->prepared_query("
                DELETE FROM artists_similar_scores
                WHERE SimilarID IN ($placeholders)
            ", ...$similarIDs);
            $this->db->prepared_query("
                DELETE FROM artists_similar_votes
                WHERE SimilarID IN ($placeholders)
            ", ...$similarIDs);
        }

        return array_keys($torrents);
    }
}
