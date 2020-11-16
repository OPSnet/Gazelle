<?php

namespace Gazelle\Manager;

class User extends \Gazelle\Base {
    /**
     * Get a User object based on a magic field (id or @name)
     *
     * @param mixed name (numeric ID or @username)
     * @return \Gazelle\User object or null if not found
     */
    public function find($name) {
        if (substr($name, 0, 1) === '@') {
            return $this->findByUsername(substr($name, 1));
        } elseif ((int)$name > 0) {
            return $this->findById((int)$name);
        }
        return null;
    }

    /**
     * Get a User object based on their ID
     *
     * @param int userId
     * @return \Gazelle\User object or null if not found
     */
    public function findById(int $userId): ?\Gazelle\User {
        $userId = (int)$this->db->scalar("
            SELECT ID
            FROM users_main
            WHERE ID = ?
            ", $userId
        );
        return $userId ? new \Gazelle\User($userId) : null;
    }

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
        return $userId ? new \Gazelle\User($userId) : null;
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

    /**
     * Get the table joins for looking at users on ratio watch
     *
     * @return string SQL table joins
     */
    protected function sqlRatioWatchJoins(): string {
        return "FROM users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
            WHERE ui.RatioWatchEnds > now()
                AND um.Enabled = '1'";
    }

    /**
     * How many people are on ratio watch?
     *
     * return int number of users
     */
    public function totalRatioWatchUsers(): int {
        return $this->db->scalar("SELECT count(*) " . $this->sqlRatioWatchJoins());
    }

    /**
     * Get details of people on ratio watch
     *
     * @return array user details
     */
    public function ratioWatchUsers(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT um.ID              AS user_id,
                uls.Uploaded          AS uploaded,
                uls.Downloaded        AS downloaded,
                ui.JoinDate           AS join_date,
                ui.RatioWatchEnds     AS ratio_watch_ends,
                ui.RatioWatchDownload AS ratio_watch_downloaded,
                um.RequiredRatio      AS required_ratio
            " . $this->sqlRatioWatchJoins() . "
            ORDER BY ui.RatioWatchEnds ASC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * How many users are banned for inadequate ratio?
     *
     * @return int number of users
     */
    public function totalBannedForRatio(): int {
        return $this->db->scalar("
            SELECT count(*) FROM users_info WHERE BanDate IS NOT NULL AND BanReason = '2'
        ");
    }
}
