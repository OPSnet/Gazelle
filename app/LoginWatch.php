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
        $this->pg()->query("
            insert into history_login_fail
                   (ip, username)
            values ($1, $2)
            ", $this->ipaddr, $username
        );
        $seen = match ($this->userId) {
            0       => false,
            default => $this->pg()->query("
                select 1
                from ip_site_history
                where upper(seen) > now() - '1 week'::interval
                    and id_user = $1
                    and ip = $2
                union
                select 1
                from ip_history
                where upper(seen) > now() - '1 week'::interval
                    and data_origin not in ('login-fail', 'tracker')
                    and id_user = $1
                    and ip = $2
                ", $userId, $this->ipaddr
            ) > 0
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
                $this->userId, substr(urlencode($username), 0, 80), $this->id
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

    public function header(): Util\SortableTableHeader {
        return new Util\SortableTableHeader(
            'last_attempt',
            [
                'ipaddr'       => ['dbColumn' => 'inet_aton(w."IP")', 'defaultSort' => 'asc',  'text' => 'IP'],
                'user'         => ['dbColumn' => 'coalesce(um."username", w."capture")', 'defaultSort' => 'asc', 'text' => 'User'],
                'attempts'     => ['dbColumn' => 'w."Attempts"',      'defaultSort' => 'desc', 'text' => 'Attempts'],
                'bans'         => ['dbColumn' => 'w."Bans"',          'defaultSort' => 'desc', 'text' => 'Bans'],
                'last_attempt' => ['dbColumn' => 'w."LastAttempt"',   'defaultSort' => 'desc', 'text' => 'Last Attempt'],
                'banned_until' => ['dbColumn' => 'w."BannedUntil"',   'defaultSort' => 'desc', 'text' => 'Login Forbidden'],
            ]
        );
    }

    /**
     * Get the list of login failures
     *
     * @return array list [ID, ipaddr, userid, LastAttempt (datetime), Attempts, BannedUntil (datetime), Bans]
     */
    public function activeList(int $limit, int $offset): array {
        return $this->pg()->allByKeyex('id', <<<END_SQL
            SELECT w."ID"       AS id,
                w."IP"          AS ipaddr,
                w."UserID"      AS user_id,
                w."LastAttempt" AS last_attempt,
                w."Attempts"    AS attempts,
                w."BannedUntil" AS banned_until,
                w."Bans"        AS bans,
                w."capture",
                um."Username"   AS username,
                (ib.ip is not null) AS banned
            FROM relay.login_attempts w
            LEFT JOIN relay.users_main um ON (um."ID" = w."UserID")
            LEFT JOIN ip_ban ib on (ib.ip::text = w."IP" and ib.is_active = true)
            WHERE (w."BannedUntil" > now() - interval '24 hour' AND w."Bans" > 0)
                OR (w."LastAttempt" > now() - interval '6 hour' AND w."Attempts" > 0)
            order by {$this->header()->orderBy()} {$this->header()->dir()}
            limit $1 offset $2
            END_SQL, $limit, $offset
        );
    }

    /**
     * Ban the IP addresses pointed to by the IDs that are on login watch.
     */
    public function setBan(
        string $reason,
        array $list,
        User $user,
        Manager\Ban $manager = new Manager\Ban(),
    ): int {
        $created = 0;
        foreach ($list as $id) {
            $ip = self::$db->scalar("
                SELECT IP FROM login_attempts WHERE ID = ?
                ", $id
            );
            if (is_string($ip)) {
                $manager->create($ip, $reason, $user);
                ++$created;
            }
        }
        Util\Irc::sendMessage(
            IRC_CHAN_STATUS,
            "login watch ip ban set by {$user->username()} on " . implode(', ', $list),
        );
        return $created;
    }

    /**
     * Clear the list of IDs that are on login watch.
     */
    public function setClear(array $list, User $user): int {
        if (!$list) {
            return 0;
        }
        $ipList = (string)self::$db->scalar("
            SELECT group_concat(IP SEPARATOR ', ') FROM login_attempts
            WHERE ID IN (" . placeholders($list) . ")
            ", ...$list
        );
        self::$db->prepared_query("
            DELETE FROM login_attempts
            WHERE ID IN (" . placeholders($list) . ")
            ", ...$list
        );
        $affected = self::$db->affected_rows();
        Util\Irc::sendMessage(
            IRC_CHAN_STATUS,
            "login watch clear by {$user->username()} on $ipList",
        );
        return $affected;
    }
}
