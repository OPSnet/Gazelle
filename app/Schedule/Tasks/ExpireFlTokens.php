<?php

namespace Gazelle\Schedule\Tasks;

class ExpireFlTokens extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $slop   = 1.04; // 4% overshoot on download before forced expiry
        $now    = sqltime();
        $expiry = FREELEECH_TOKEN_EXPIRY_DAYS;

        $this->db->prepared_query("
            SELECT DISTINCT uf.UserID
            FROM users_freeleeches AS uf
            INNER JOIN torrents AS t ON (t.ID = uf.TorrentID)
            WHERE (uf.Expired = FALSE OR (t.Size > 0 AND uf.Downloaded / t.Size > ?))
                AND uf.Time < ? - INTERVAL ? DAY
        ", $slop, $now, $expiry);

        if ($this->db->has_results()) {
            while (list($userID) = $this->db->next_record()) {
                $this->cache->delete_value('users_tokens_'.$userID);
                $this->debug("Clearing token cache for $userID", $userID);
            }

            $this->db->prepared_query("
                SELECT uf.UserID, t.info_hash
                FROM users_freeleeches AS uf
                INNER JOIN torrents AS t ON (t.ID = uf.TorrentID)
                WHERE (uf.Expired = FALSE OR (t.Size > 0 AND uf.Downloaded / t.Size > ?))
                    AND uf.Time < ? - INTERVAL ? DAY
            ", $slop, $now, $expiry);
            while (list($userID, $infoHash) = $this->db->next_record(MYSQLI_NUM, false)) {
                \Tracker::update_tracker('remove_token', ['info_hash' => rawurlencode($infoHash), 'userid' => $userID]);
                $this->processed++;
            }
            $this->db->prepared_query("
                UPDATE users_freeleeches
                SET Expired = TRUE
                WHERE (Expired = FALSE OR (t.Size > 0 AND uf.Downloaded / t.Size > ?))
                    AND Time < ? - INTERVAL ? DAY
            ", $slop, $now, $expiry);
        }
    }
}
