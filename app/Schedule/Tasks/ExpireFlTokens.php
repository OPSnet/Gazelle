<?php

namespace Gazelle\Schedule\Tasks;

class ExpireFlTokens extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $now = sqltime();
        $expiry = FREELEECH_TOKEN_EXPIRY_DAYS;
        $this->db->prepared_query("
            SELECT DISTINCT UserID
            FROM users_freeleeches
            WHERE Expired = FALSE
                AND Time < ? - INTERVAL ? DAY
        ", $now, $expiry);

        if ($this->db->has_results()) {
            while (list($userID) = $this->db->next_record()) {
                $this->cache->delete_value('users_tokens_'.$userID);
                $this->debug("Clearing token cache for $userID", $userID);
            }

            $this->db->prepared_query("
                SELECT uf.UserID, t.info_hash
                FROM users_freeleeches AS uf
                INNER JOIN torrents AS t ON (uf.TorrentID = t.ID)
                WHERE uf.Expired = FALSE
                    AND uf.Time < ? - INTERVAL ? DAY
            ", $now, $expiry);
            while (list($userID, $infoHash) = $this->db->next_record(MYSQLI_NUM, false)) {
                \Tracker::update_tracker('remove_token', ['info_hash' => rawurlencode($infoHash), 'userid' => $userID]);
                $this->processed++;
            }
            $this->db->prepared_query("
                UPDATE users_freeleeches
                SET Expired = TRUE
                WHERE Time < ? - INTERVAL ? DAY
                    AND Expired = FALSE
            ", $now, $expiry);
        }
    }
}
