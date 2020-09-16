<?php

namespace Gazelle;

class LoginWatch extends Base {
    protected $watchId;

    /**
     * Set the context of a watched IP address (to save passing it in to each method call).
     * On a virgin login with no previous errors there may not even be a watch yet
     * @param int ID of the watch
     */
    public function setWatch($watchId) {
        if (!is_null($watchId)) {
            $this->watchId = $watchId;
        }
        return $this;
    }

    /**
     * Find a login watch by IP address
     * @param string IPv4 address
     * @return array [watchId, nrAttemtps, nrBans, bannedUntil]
     */
    public function findByIp(string $ipaddr): ?array {
        return $this->db->row("
            SELECT ID, Attempts, Bans, BannedUntil
            FROM login_attempts
            WHERE IP = ?
            ", $_SERVER['REMOTE_ADDR']
        );
    }

    /**
     * Create a new login watch on an userid/username/ipaddress
     * @param string IPv4 address
     * @param string $capture The username captured on the form
     * @param int $userId
     * @return int ID of watch
     */
    public function create(string $ipaddr, string $capture, int $userId = 0) {
        $this->db->prepared_query("
            INSERT INTO login_attempts
                   (IP, capture, UserID)
            VALUES (?,  ?,       ?)
            ", $ipaddr, $capture, $userId
        );
        return ($this->watchId = $this->db->inserted_id());
    }

    /**
     * Record another failure attempt on this watch
     * @param int $userId The ID of the user
     * @param string $capture The username captured on the form
     * @return int 1 if the watch was updated
     */
    public function increment(int $UserID, string $capture): int {
        $this->db->prepared_query('
            UPDATE login_attempts SET
                LastAttempt = now(),
                Attempts = Attempts + 1,
                UserID = ?,
                capture = ?
            WHERE ID = ?
            ', $UserID, $capture, $this->watchId
        );
        return $this->db->affected_rows();
    }

    /**
     * Ban subsequent attempts to login from this watched IP address for 6 hours
     * @param int $attempts How many attempts so far?
     * @param string the username captured on the form (which may not even be a valid user)
     * @param int $userId user ID of a valid user (or 0 if invalid username)
     * @return int 1 if the watch was banned
     */
    public function ban(int $attempts, string $capture, int $userId = 0): int {
        $this->db->prepared_query('
            UPDATE login_attempts SET
                Bans = Bans + 1,
                LastAttempt = now(),
                BannedUntil = now() + INTERVAL 6 HOUR,
                Attempts = ?,
                capture = ?,
                UserID = ?
            WHERE ID = ?
            ', $attempts, $capture, $userId, $this->watchId
        );
        return $this->db->affected_rows();
    }

    /**
     * When does the login ban expire?
     * @return string datestamp of expiry
     */
    public function bannedUntil(): ?string {
        return $this->db->scalar("
            SELECT BannedUntil FROM login_attempts WHERE ID = ?
            ", $this->watchId
        );
    }

    /**
     * If the login ban was in the past then they get 6 more shots
     * @return int 1 if a prior ban was cleared
     */
    public function clearPriorBan(): int {
        $this->db->prepared_query("
            UPDATE login_attempts SET
                BannedUntil = NULL,
                Attempts = 0
            WHERE BannedUntil < now() AND ID = ?
            ", $this->watchId
        );
        return $this->db->affected_rows();
    }

    /**
     * If the login was successful, clear prior attempts
     * @return int 1 if an update was made
     */
    public function clearAttempts(): int {
        $this->db->prepared_query("
            UPDATE login_attempts SET
                Attempts = 0
            WHERE ID = ?
            ", $this->watchId
        );
        return $this->db->affected_rows();
    }

    /**
     * How many attempts have been made on this watch?
     * @return int Number of attempts
     */
    public function nrAttempts(): int {
        return (int)$this->db->scalar("
            SELECT Attempts FROM login_attempts WHERE ID = ?
            ", $this->watchId
        ) ?? 0;
    }

    /**
     * Get the list of login failures
     * @return array list [ID, ipaddr, userid, LastAttempt (datetime), Attempts, BannedUntil (datetime), Bans]
     */
    public function activeList(string $orderBy, string $orderWay): array {
        $this->db->prepared_query("
            SELECT
                w.ID          AS id,
                w.IP          AS ipaddr,
                w.UserID      AS user_id,
                w.LastAttempt AS last_attempt,
                w.Attempts    AS attempts,
                w.BannedUntil AS banned_until,
                w.Bans        AS bans,
                w.capture,
                um.Username   AS username,
                (ip.FromIP IS NOT NULL) AS banned
            FROM login_attempts w
            LEFT JOIN users_main um ON (um.ID = w.UserID)
            LEFT JOIN ip_bans ip ON (ip.FromIP = inet_aton(w.IP))
            WHERE (w.BannedUntil > now() OR w.LastAttempt > now() - INTERVAL 6 HOUR)
            ORDER BY $orderBy $orderWay
        ");
        return $this->db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Ban the IP addresses pointed to by the IDs that are on login watch.
     * @param array list of IDs to ban.
     * @return number of addresses banned
     */
    public function setBan(int $userId, string $reason, array $list): int {
        if (!$list) {
            return 0;
        }
        $reason = trim($reason);
        $n = 0;
        foreach ($list as $id) {
            $ipv4 = $this->db->scalar("
                SELECT inet_aton(IP) FROM login_attempts WHERE ID = ?
                ", $id
            );
            $this->db->prepared_query("
                INSERT IGNORE INTO ip_bans
                       (user_id, Reason, FromIP, ToIP)
                VALUES (?,       ?,      ?,      ?)
                ", $userId, $reason, $ipv4, $ipv4
            );
            $n += $this->db->affected_rows();
        }
        return $n;
    }

    /**
     * Clear the list of IDs that are on login watch.
     * @param array list of IDs to clear.
     * @return number of rows removed
     */
    public function setClear(array $list): int {
        if (!$list) {
            return 0;
        }
        $this->db->prepared_query("
            DELETE FROM login_attempts
            WHERE ID in (" . placeholders($list) . ")
            ", ... $list
        );
        return $this->db->affected_rows();
    }
}
