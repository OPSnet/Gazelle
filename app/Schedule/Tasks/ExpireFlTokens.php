<?php

namespace Gazelle\Schedule\Tasks;

class ExpireFlTokens extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $slop   = 1.04; // 4% overshoot on download before forced expiry
        $now    = sqltime();
        $expiry = FREELEECH_TOKEN_EXPIRY_DAYS;

        self::$db->prepared_query("
            SELECT DISTINCT uf.UserID
            FROM users_freeleeches AS uf
            INNER JOIN torrents AS t ON (t.ID = uf.TorrentID)
            WHERE (uf.Expired = FALSE OR (t.Size > 0 AND uf.Downloaded / t.Size > ?))
                AND uf.Time < ? - INTERVAL ? DAY
        ", $slop, $now, $expiry);

        if (self::$db->has_results()) {
            while (list($userID) = self::$db->next_record()) {
                self::$cache->delete_value('users_tokens_'.$userID);
                $this->debug("Clearing token cache for $userID", $userID);
            }

            self::$db->prepared_query("
                SELECT uf.UserID, t.info_hash
                FROM users_freeleeches AS uf
                INNER JOIN torrents AS t ON (t.ID = uf.TorrentID)
                WHERE (uf.Expired = FALSE OR (t.Size > 0 AND uf.Downloaded / t.Size > ?))
                    AND uf.Time < ? - INTERVAL ? DAY
            ", $slop, $now, $expiry);
            $tracker = new \Gazelle\Tracker;
            while (list($userID, $infoHash) = self::$db->next_record(MYSQLI_NUM, false)) {
                $tracker->update_tracker('remove_token', ['info_hash' => rawurlencode($infoHash), 'userid' => $userID]);
                $this->processed++;
            }
            self::$db->prepared_query("
                UPDATE users_freeleeches uf
                INNER JOIN torrents AS t ON (t.ID = uf.TorrentID) SET
                    uf.Expired = TRUE
                WHERE (uf.Expired = FALSE OR (t.Size > 0 AND uf.Downloaded / t.Size > ?))
                    AND uf.time < ? - INTERVAL ? DAY
            ", $slop, $now, $expiry);
        }
    }
}
