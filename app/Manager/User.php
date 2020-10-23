<?php

namespace Gazelle\Manager;

class User extends \Gazelle\Base {
    /**
     * Get a User object based on their username
     *
     * @param string username
     * @return \Gazelle\User object or null if not found
     */
    public function findByUsername(string $username): ?\Gazelle\User {
        $userId = (int)$this->db->scalar("
            SELECT ID
            FROM users_main
            WHERE Username = ?
            ", $username
        );
        return is_null($userId) ? null : new \Gazelle\User($userId);
    }

    /**
     * Get the number of enabled users last day/week/month
     *
     * @return array [Day, Week, Month]
     */
    public function globalActivityStats(): array {
        if (($stats = $this->cache->get_value('stats_users')) === false) {
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
        if (($count = $this->cache->get_value('stats_user_count')) == false) {
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

    /**
     * Disable a user from being able to use invites
     *
     * @param int user id
     * @return bool success (if invite status was changed)
     */
    public function disableInvites(int $userId): bool {
        $this->db->prepared_query("
            UPDATE users_info SET
                DisableInvites = '1'
            WHERE DisableInvites = '0'
                AND UserID = ?
            ", $userId
        );
        return $this->db->affected_rows() === 1;
    }
}
