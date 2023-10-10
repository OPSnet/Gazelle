<?php

namespace Gazelle\Manager;

class DuplicateIP extends \Gazelle\Base {
    public function total(int $threshold): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM users_main AS um
            WHERE um.Enabled = '1'
                AND um.IP != '127.0.0.1'
                AND (
                    SELECT count(DISTINCT h.UserID)
                    FROM users_history_ips AS h
                    WHERE h.IP = um.IP
                ) >= ?
            ", $threshold
        );
    }

    public function page(int $threshold, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT um.ID   AS user_id,
                um.IP      AS ipaddr,
                um.created AS created,
                (
                    SELECT count(DISTINCT h.UserID)
                    FROM users_history_ips AS h
                    WHERE h.IP = um.IP
                ) AS uses
            FROM users_main AS um
            WHERE um.Enabled = '1'
                AND um.IP != '127.0.0.1'
                AND (
                    SELECT count(DISTINCT h.UserID)
                    FROM users_history_ips AS h
                    WHERE h.IP = um.IP
                ) >= ?
            ORDER BY Uses DESC
            LIMIT ? OFFSET ?
            ", $threshold, $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
