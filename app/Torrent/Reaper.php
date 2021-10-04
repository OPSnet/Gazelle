<?php

namespace Gazelle\Torrent;

class Reaper extends \Gazelle\Base {

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
            SELECT t.ID
            FROM torrents AS t
            INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
            WHERE $criteria
            LIMIT 8000
        ");
        $torrents = $this->db->collect('ID');

        $logEntries = $deleteNotes = [];
        $torMan = new \Gazelle\Manager\Torrent;
        $labelMan = new \Gazelle\Manager\TorrentLabel;
        $labelMan->showMedia(true)->showEdition(true);

        $i = 0;
        foreach ($torrents as $id) {
            $t = $torMan->findById($id);
            $torrent = $t->info();
            $group   = $t->group()->info();
            $name = $group['Name'] . " " . $labelMan->load($torrent)->edition();

            $artistName = $t->group()->artistName();
            if ($artistName) {
                $name = "$artistName - $name";
            }

            [$success, $message] = $t->remove(0, 'inactivity (unseeded)');
            if (!$success) {
                continue;
            }
            $log = "Torrent $id ($name) (" . strtoupper($torrent['InfoHash']) . ") was deleted for inactivity (unseeded)";
            $logEntries[] = $log;

            $userID = $torrent['UserID'];
            if (!array_key_exists($userID, $deleteNotes)) {
                $deleteNotes[$userID] = ['Count' => 0, 'Msg' => ''];
            }

            $deleteNotes[$userID]['Msg'] .= sprintf("\n[url=torrents.php?id=%s]%s[/url]", $group['ID'], $name);
            $deleteNotes[$userID]['Count']++;

            ++$i;
        }

        $userMan = new \Gazelle\Manager\User;
        foreach ($deleteNotes as $userID => $messageInfo) {
            $singular = (($messageInfo['Count'] == 1) ? true : false);
            $userMan->sendPM( $userID, 0,
                $messageInfo['Count'].' of your torrents '.($singular ? 'has' : 'have').' been deleted for inactivity',
                ($singular ? 'One' : 'Some').' of your uploads '.($singular ? 'has' : 'have').' been deleted for being unseeded. Since '.($singular ? 'it' : 'they').' didn\'t break any rules (we hope), please feel free to re-upload '.($singular ? 'it' : 'them').".\n\nThe following torrent".($singular ? ' was' : 's were').' deleted:'.$messageInfo['Msg']
            );
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
