<?php

namespace Gazelle\Manager;

class User extends \Gazelle\Base {
    /**
     * Get the number of enabled users last day/week/month
     *
     * @return array [Day, Week, Month]
     */
    public function globalActivityStats(): array {
        if (($stats = $cache->get_value('stats_users')) === false) {
            $this->db->prepared_query("
                SELECT
                    sum(ula.last_access > now() - INTERVAL 1 DAY) AS Day,
                    sum(ula.last_access > now() - INTERVAL 1 WEEK) AS Week,
                    sum(ula.last_access > now() - INTERVAL 1 MONTH) AS Month
                FROM users_main um
                INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
                WHERE um.Enabled = '1'
                    AND ula.last_access > now() - INTERVAL 1 MONTH
            ");
            $stats = $this->db->next_record(MYSQLI_ASSOC);
            $this->cache->cache_value('stats_users', $stats, 7200);
        }
        return $stats;
    }

    /**
     * Get the count of enabled users.
     *
     * @return integer Number of enabled users (this is cached).
     */
    public function getEnabledUsersCount(): int {
        if (($count = G::$Cache->get_value('stats_user_count')) == false) {
            $count = $this->db->scalar("SELECT count(*) FROM users_main WHERE Enabled = '1'");
            $this->cache->cache_value('stats_user_count', $count, 0);
        }
        return $count;
    }

    /**
     * Flush the cached count of enabled users.
     */
    public function flushEnabledUsersCount() {
        $this->cache->delete_value('stats_user_count');
        return $this;
    }
}
