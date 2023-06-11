<?php

namespace Gazelle;

class LoginWatch extends Base {
    protected int $id;
    protected int $userId = 0;

    public function __construct(protected string $ipaddr) {
        $this->id = (int)self::$db->scalar("
            SELECT ID FROM login_attempts WHERE IP = ?
            ", $this->ipaddr
        );
        if (!$this->id && $ipaddr != '0.0.0.0') {
            self::$db->prepared_query("
                INSERT INTO login_attempts
                       (IP, UserID)
                VALUES (?,  0)
                ", $ipaddr
            );
            $this->id = self::$db->inserted_id();
        }
    }

    /**
     * Record another failure attempt on this watch. If the user has not
     * logged in recently from this IP address then subsequent logins
     * will be blocked for increasingly longer times, otherwise 1 minute.
     */
    public function increment(int $userId, string $username): int {
        $this->userId = $userId;
        $seen = (bool)self::$db->scalar("
            SELECT 1
            FROM users_history_ips
            WHERE (EndTime IS NULL OR EndTime > now() - INTERVAL 1 WEEK)
                AND UserID = ?
                AND IP = ?
            ", $this->userId, $this->ipaddr
        );
        self::$db->prepared_query('
            UPDATE login_attempts SET
                Attempts = Attempts + 1,
                LastAttempt = now(),
                BannedUntil = now() + INTERVAL ? SECOND,
                UserID = ?,
                capture = ?
            WHERE ID = ?
            ', $seen ? 60 : LOGIN_ATTEMPT_BACKOFF[min($this->nrAttempts(), count(LOGIN_ATTEMPT_BACKOFF)-1)],
                $this->userId, substr(urlencode($username), 0, 20), $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Ban subsequent attempts to login from this watched IP address for a while
     */
    public function ban(string $username): int {
        self::$db->prepared_query('
            UPDATE login_attempts SET
                Bans = Bans + 1,
                LastAttempt = now(),
                BannedUntil = now() + INTERVAL 1 DAY,
                capture = ?,
                UserID = ?
            WHERE ID = ?
            ', substr($username, 0, 20), $this->userId, $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * When does the login ban expire?
     */
    public function bannedUntil(): ?string {
        $until = self::$db->scalar("
            SELECT BannedUntil FROM login_attempts WHERE ID = ?
            ", $this->id
        );
        return $until ? (string)$until : null;
    }

    /**
     * When does the login ban expire?
     */
    public function bannedEpoch(): int|false {
        return strtotime($this->bannedUntil());
    }

    /**
     * If the login ban was in the past then they get 6 more shots
     */
    public function clearPriorBan(): int {
        self::$db->prepared_query("
            UPDATE login_attempts SET
                BannedUntil = NULL,
                Attempts = 0
            WHERE BannedUntil < now() AND ID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * If the login was successful, clear prior attempts
     */
    public function clearAttempts(): int {
        self::$db->prepared_query("
            UPDATE login_attempts SET
                Attempts = 0
            WHERE ID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * How many attempts have been made on this watch?
     */
    public function nrAttempts(): int {
        return (int)self::$db->scalar("
            SELECT Attempts FROM login_attempts WHERE ID = ?
            ", $this->id
        );
    }

    /**
     * How many bans have been made on this watch?
     */
    public function nrBans(): int {
        return (int)self::$db->scalar("
            SELECT Bans FROM login_attempts WHERE IP = ?
            ", $this->ipaddr
        );
    }

    /**
     * Get total login failures
     */
    public function activeTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM login_attempts w
            WHERE (w.BannedUntil > now() OR w.LastAttempt > now() - INTERVAL 6 HOUR)
        ");
    }

    /**
     * Get the list of login failures
     *
     * @return array list [ID, ipaddr, userid, LastAttempt (datetime), Attempts, BannedUntil (datetime), Bans]
     */
    public function activeList(string $orderBy, string $orderWay, int $limit, int $offset): array {
        self::$db->prepared_query("
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
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Ban the IP addresses pointed to by the IDs that are on login watch.
     */
    public function setBan(int $userId, string $reason, array $list): int {
        if (!$list) {
            return 0;
        }
        $reason = trim($reason);
        $affected = 0;
        foreach ($list as $id) {
            $ipv4 = self::$db->scalar("
                SELECT inet_aton(IP) FROM login_attempts WHERE ID = ?
                ", $this->id
            );
            self::$db->prepared_query("
                INSERT IGNORE INTO ip_bans
                       (user_id, Reason, FromIP, ToIP)
                VALUES (?,       ?,      ?,      ?)
                ", $userId, substr($reason, 0, 255), $ipv4, $ipv4
            );
            $affected += self::$db->affected_rows();
        }
        return $affected;
    }

    /**
     * Clear the list of IDs that are on login watch.
     */
    public function setClear(array $list): int {
        if (!$list) {
            return 0;
        }
        self::$db->prepared_query("
            DELETE FROM login_attempts
            WHERE ID in (" . placeholders($list) . ")
            ", ...$list
        );
        return self::$db->affected_rows();
    }
}
