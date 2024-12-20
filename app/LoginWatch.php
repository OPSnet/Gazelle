<?php

namespace Gazelle;

class LoginWatch extends Base {
    use Pg;

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

    public function id(): int {
        return $this->id;
    }

    /**
     * Record another failure attempt on this watch. If the user has not
     * logged in recently from this IP address then subsequent logins
     * will be blocked for increasingly longer times, otherwise 1 minute.
     */
    public function increment(int $userId, string $username): int {
        $this->userId = $userId;
        $this->pg()->prepared_query("
            insert into ip_history
                   (id_user, ip, data_origin)
            values (?,       ?,  'login-fail')
            on conflict (id_user, ip, data_origin) do update set
                total = ip_history.total + 1,
                seen = tstzrange(lower(ip_history.seen), now())
            ", $userId, $this->ipaddr
        );
        $seen = match ($this->userId) {
            0       => false,
            default => (bool)self::$db->scalar("
                    SELECT 1
                    FROM users_history_ips
                    WHERE (EndTime IS NULL OR EndTime > now() - INTERVAL 1 WEEK)
                        AND UserID = ?
                        AND IP = ?
                    ", $this->userId, $this->ipaddr
                )
        };
        self::$db->prepared_query('
            UPDATE login_attempts SET
                Attempts = Attempts + 1,
                LastAttempt = now(),
                BannedUntil = now() + INTERVAL ? SECOND,
                UserID = ?,
                capture = ?
            WHERE ID = ?
            ', $seen ? 60 : LOGIN_ATTEMPT_BACKOFF[min($this->nrAttempts(), count(LOGIN_ATTEMPT_BACKOFF) - 1)],
                $this->userId, substr($username, 0, 20), $this->id
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

    public function capture(): ?string {
        /* @phpstan-ignore-next-line query cannot return bad type */
        return self::$db->scalar("
            SELECT capture FROM login_attempts WHERE ID = ?
            ", $this->id
        );
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

    public function ipaddr(): string {
        return $this->ipaddr;
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
            WHERE (w.BannedUntil > now() - INTERVAL 24 HOUR AND Bans > 0)
                OR (w.LastAttempt > now() - INTERVAL 6 HOUR AND attempts > 0)
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
            WHERE (w.BannedUntil > now() - INTERVAL 24 HOUR AND Bans > 0)
                OR (w.LastAttempt > now() - INTERVAL 6 HOUR AND attempts > 0)
            ORDER BY $orderBy $orderWay
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Ban the IP addresses pointed to by the IDs that are on login watch.
     */
    public function setBan(User $user, string $reason, array $list, Manager\IPv4 $manager): int {
        if (!$list) {
            return 0;
        }
        $affected = 0;
        foreach ($list as $id) {
            $ipv4 = self::$db->scalar("
                SELECT IP FROM login_attempts WHERE ID = ?
                ", $id
            );
            if (is_string($ipv4)) {
                $affected += $manager->createBan($user, $ipv4, $ipv4, $reason);
            }
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
