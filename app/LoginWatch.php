<?php

namespace Gazelle;

class LoginWatch extends Base {
    protected $id;
    protected $ipaddr;
    protected $userId = 0;

    public function __construct(string $ipaddr) {
        parent::__construct();
        $this->ipaddr = $ipaddr;
        $this->id = $this->db->scalar("
            SELECT ID FROM login_attempts WHERE IP = ?
            ", $this->ipaddr
        );
        if (is_null($this->id) && $ipaddr != '0.0.0.0') {
            $this->db->prepared_query("
                INSERT INTO login_attempts
                       (IP, UserID)
                VALUES (?,  0)
                ", $ipaddr
            );
            $this->id = $this->db->inserted_id();
        }
    }

    /**
     * Record another failure attempt on this watch. If the user has not
     * logged in recently from this IP address then subsequent logins
     * will be blocked for increasingly longer times, otherwise 1 minute.
     *
     * @param int $userId The ID of the user
     * @return int 1 if the watch was updated
     */
    public function increment(int $userId, string $username): int {
        $this->userId = $userId;
        $seen = (bool)$this->db->scalar("
            SELECT 1
            FROM users_history_ips
            WHERE (EndTime IS NULL OR EndTime > now() - INTERVAL 1 WEEK)
                AND UserID = ?
                AND IP = ?
            ", $this->userId, $this->ipaddr
        );
        $this->db->prepared_query('
            UPDATE login_attempts SET
                Attempts = Attempts + 1,
                LastAttempt = now(),
                BannedUntil = now() + INTERVAL ? SECOND,
                UserID = ?,
                capture = ?
            WHERE ID = ?
            ', $seen ? 60 : LOGIN_ATTEMPT_BACKOFF[min($this->nrAttempts(), count(LOGIN_ATTEMPT_BACKOFF)-1)],
                $this->userId, $username, $this->id
        );
        return $this->db->affected_rows();
    }

    /**
     * Ban subsequent attempts to login from this watched IP address for a while
     * @return int 1 if the watch was banned
     */
    public function ban(string $username): int {
        $this->db->prepared_query('
            UPDATE login_attempts SET
                Bans = Bans + 1,
                LastAttempt = now(),
                BannedUntil = now() + INTERVAL 1 DAY,
                capture = ?,
                UserID = ?
            WHERE ID = ?
            ', $username, $this->userId, $this->id
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
            ", $this->id
        );
    }

    /**
     * When does the login ban expire?
     * @return int epoch
     */
    public function bannedEpoch(): int {
        return strtotime($this->bannedUntil()) ?? 0;
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
            ", $this->id
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
            ", $this->id
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
            ", $this->id
        );
    }

    /**
     * How many bans have been made on this watch?
     * @return int Number of attempts
     */
    public function nrBans(): int {
        return (int)$this->db->scalar("
            SELECT Bans FROM login_attempts WHERE IP = ?
            ", $this->ipaddr
        );
    }

    /**
     * Get total login failures
     * @return int count
     */
    public function activeTotal(): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM login_attempts w
            WHERE (w.BannedUntil > now() OR w.LastAttempt > now() - INTERVAL 6 HOUR)
        ");
    }

    /**
     * Get the list of login failures
     * @return array list [ID, ipaddr, userid, LastAttempt (datetime), Attempts, BannedUntil (datetime), Bans]
     */
    public function activeList(string $orderBy, string $orderWay, int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT w.ID       AS id,
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
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return $this->db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Ban the IP addresses pointed to by the IDs that are on login watch.
     * @param array list of IDs to ban.
     * @return number of addresses banned
     */
    public function setBan(string $reason, array $list): int {
        if (!$list) {
            return 0;
        }
        $reason = trim($reason);
        $n = 0;
        foreach ($list as $id) {
            $ipv4 = $this->db->scalar("
                SELECT inet_aton(IP) FROM login_attempts WHERE ID = ?
                ", $this->id
            );
            $this->db->prepared_query("
                INSERT IGNORE INTO ip_bans
                       (user_id, Reason, FromIP, ToIP)
                VALUES (?,       ?,      ?,      ?)
                ", $this->userId, $reason, $ipv4, $ipv4
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
