<?php

namespace Gazelle;

/* Note: the LoginWatch class does not know how to delete rows
 * in the login_watch table. This is handled by a scheduled task.
 */

class LoginWatch extends Base {
    public function __construct(public readonly string $ipaddr) {
        $this->pg()->query('
            insert into login_watch (ip) values ($1)
            on conflict (ip) do nothing
            ', $this->ipaddr
        );
    }

    /**
     * Record another failure attempt on this watch. If the user has not
     * logged in recently from this IP address then subsequent logins
     * will be blocked for increasingly longer times, otherwise 1 minute.
     */
    public function increment(int|null $userId, string $username): int {
        $this->pg()->query("
            insert into history_login_fail
                   (ip, username)
            values ($1, $2)
            ", $this->ipaddr, $username
        );
        $seen = match ($userId) {
            null    => false,
            default => $this->pg()->scalarex("
                select 1
                from ip_site_history
                where upper(seen) > now() - interval '1 week'
                    and id_user = $1
                    and ip      = $2
                union
                select 1
                from ip_history
                where upper(seen) > now() - interval '1 week'
                    and data_origin != 'tracker'
                    and id_user = $1
                    and ip      = $2
                ", $userId, $this->ipaddr
            ) > 0
        };
        return $this->pg()->query(<<<END_SQL
            update login_watch set
                attempt       = attempt + 1,
                blocked_until = now() + interval '1 second' * $1,
                id_user       = $2,
                capture       = $3
            where ip = $4
            END_SQL,
                $seen ? 60 : LOGIN_ATTEMPT_BACKOFF[min($this->attemptTotal(), count(LOGIN_ATTEMPT_BACKOFF) - 1)],
                $userId,
                substr($username, 0, 80),
                $this->ipaddr,
        );
    }

    /**
     * Block subsequent attempts to login from this watched IP address for a while
     */
    public function block(int|null $userId, string $username): int {
        return $this->pg()->query(<<<END_SQL
            update login_watch set
                block         = block + 1,
                blocked_until = case when blocked_until > now()
                                then blocked_until
                                else now() + interval '1 day' end,
                id_user       = $1,
                capture       = $2
            where ip = $3
            END_SQL,
                $userId,
                substr($username, 0, 80),
                $this->ipaddr,
        );
    }

    /**
     * When does the login ban expire?
     */
    public function blockedUntil(): \DateTimeImmutable|null {
        return $this->pg()->scalarex('
            select blocked_until from login_watch where ip = $1
            ', $this->ipaddr
        );
    }

    /**
     * Clear an active block.
     */
    public function unblock(): int {
        return $this->pg()->query('
            update login_watch set
                blocked_until = null,
                attempt       = 0
            where blocked_until >= now() and ip = $1
            ', $this->ipaddr
        );
    }

    public function capture(): ?string {
        $capture = $this->pg()->scalarex('
            select capture from login_watch where ip = $1
            ', $this->ipaddr
        );
        return is_null($capture) ? null : (string)$capture;
    }

    /**
     * If the login was successful, clear prior attempts
     */
    public function clearAttempt(): int {
        return $this->pg()->query('
            update login_watch set
                attempt = 0
            where ip = $1
            ', $this->ipaddr
        );
    }

    /**
     * How many attempts have been made on this watch?
     */
    public function attemptTotal(): int {
        return (int)$this->pg()->scalarex('
            select attempt from login_watch where ip = $1
            ', $this->ipaddr
        );
    }

    /**
     * How many bans have been made on this watch?
     */
    public function blockTotal(): int {
        return (int)$this->pg()->scalarex('
            select block from login_watch where ip = $1
            ', $this->ipaddr
        );
    }
}
