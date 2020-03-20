<?php

namespace Gazelle\Schedule\Tasks;

class RewardPerfectFlacUploads extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
            SELECT u.ID, u.Username, COUNT(t.UserID) AS c, u.FLT_Given, u.Invites_Given, u.Invites, u.FLTokens
            FROM torrents t 
            INNER JOIN users_main u ON (t.UserID = u.ID)
            WHERE (t.HasLog = '1' AND t.LogScore = 100) OR (t.Media = 'Vinyl' OR t.Media = 'WEB' OR t.Media = 'DVD' OR t.Media = 'SACD' OR t.Media = 'BD') AND (t.Format = 'FLAC') 
            GROUP BY t.UserID ORDER BY c DESC");

        if ($this->db->has_results()) {
            while (list($userID, $username, $count, $fltGiven, $invitesGiven, $curInvites, $curFLTokens) = $this->db->next_record()) {
                $flTokens = max(floor($count / 5) - $fltGiven, 0);
                $invites = max(floor($count / 20) - $invitesGiven, 0);
                if ($flTokens != 0) {
                    $this->db->prepared_query("
                        UPDATE users_main
                        SET FLTokens = FLTokens + ?,
                            Invites = Invites + ?,
                            FLT_Given = FLT_Given + ?,
                            Invites_Given = Invites_Given + ?
                        WHERE ID = ?
                    ", $flTokens, $invites, $flTokens, $invites, $userID);

                    $this->processed++;
                    $this->debug("Giving $userID $invites invites and $flTokens tokens", $userID);

                    $invites = $invites + $curInvites;
                    $flTokens = $flTokens + $curFLTokens;
                    $this->cache->begin_transaction('user_info_heavy_'.$userID);
                    $this->cache->update_row(false, ['Invites' => $invites, 'FLTokens' => $flTokens]);
                    $this->cache->commit_transaction(0);
                }
            }
        }
    }
}
