<?php

namespace Gazelle\Manager;

use Gazelle\User       as User;
use Gazelle\Util\GeoIP as GeoIP;

/**
 * This class handles both the user IP site history as well as
 * the bans placed upon IP addresses.
 */

class IPv4 extends \Gazelle\Base {
    protected int $filterBefore;
    protected int $filterAfter;
    protected string $filterIpaddr;
    protected string $filterIpaddrRegexp;

    public function flush(): static {
        unset(
            $this->filterAfter,
            $this->filterBefore,
            $this->filterIpaddr,
            $this->filterIpaddrRegexp,
        );
        return $this;
    }

    public function register(User $user, string $ipv4): int {
        $affected = $this->pg()->executeParams("
            insert into ip_history
                   (id_user, ip, data_origin)
            values ($1,      $2, 'token')
            on conflict (id_user, ip, data_origin) do update set
                total = ip_history.total + 1,
                seen = tstzrange(lower(ip_history.seen), now())
            ", $user->id, $ipv4
        )->getAffectedRows();
        self::$db->prepared_query('
            INSERT INTO users_history_ips
                   (UserID, IP)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE EndTime = now()
            ', $user->id, $ipv4
        );
        $user->setField('IP', $ipv4)
            ->setField('ipcc', new GeoIP()->countryISO($ipv4))
            ->modify();
        $this->flush();
        return $affected;
    }

    /**
     * Include records whose StartTime is prior to this many seconds ago.
     */
    public function setFilterBefore(int $delta): static {
        $this->filterBefore = $delta;
        return $this;
    }

    /**
     * Include records whose StartTime is after than this many seconds ago.
     */
    public function setFilterAfter(int $delta): static {
        $this->filterAfter   = $delta;
        return $this;
    }

    public function setFilterIpaddr(string $filterIpaddr): static {
        $this->filterIpaddr = $filterIpaddr;
        return $this;
    }

    public function setFilterIpaddrRegexp(string $filterIpaddrRegexp): static {
        $this->filterIpaddrRegexp = $filterIpaddrRegexp;
        return $this;
    }

    public function userTotalConfig(User $user): array {
        $cond = ['uhi.UserID = ?'];
        $args = [$user->id];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = "uhi.IP REGEXP ?";
            $args[] = $this->filterIpaddrRegexp;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = "uhi.IP = ?";
            $args[] = $this->filterIpaddr;
        }
        if (isset($this->filterBefore)) {
            $cond[] = "uhi.StartTime <= now() - INTERVAL ? SECOND";
            $args[] = $this->filterBefore;
        }
        if (isset($this->filterAfter)) {
            $cond[] = "uhi.StartTime >= now() - INTERVAL ? SECOND";
            $args[] = $this->filterAfter;
        }
        return [
            "SELECT count(DISTINCT IP) FROM users_history_ips uhi WHERE "
                . implode(' AND ', $cond),
            $args
        ];
    }

    public function userTotal(User $user): int {
        [$sql, $args] = $this->userTotalConfig($user);
        return (int)self::$db->scalar($sql, ...$args);
    }

    public function userOtherConfig(User $user): array {
        $cond = ['uhi.UserID != ?'];
        $args = [$user->id];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = "uhi.IP REGEXP ?";
            $args[] = $this->filterIpaddrRegexp;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = "uhi.IP = ?";
            $args[] = $this->filterIpaddr;
        }
        if (isset($this->filterBefore)) {
            $cond[] = "uhi.StartTime <= now() - INTERVAL ? SECOND";
            $args[] = $this->filterBefore;
        }
        if (isset($this->filterAfter)) {
            $cond[] = "uhi.StartTime >= now() - INTERVAL ? SECOND";
            $args[] = $this->filterAfter;
        }
        return [
            "SELECT DISTINCT UserID FROM users_history_ips uhi WHERE "
                . implode(' AND ', $cond),
            $args,
        ];
    }

    /**
     * returns array of userids that match filters, excluding specified user
     */
    public function userOther(User $user): array {
        [$sql, $args] = $this->userOtherConfig($user);
        self::$db->prepared_query($sql, ...$args);
        return self::$db->collect(0);
    }

    public function userPage(User $user, int $limit, int $offset): array {
        self::$db->prepared_query("SET SESSION group_concat_max_len = 50000");
        $cond = ['i.UserID = ?'];
        $args = [$user->id];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = "i.IP REGEXP ?";
            $args[] = $this->filterIpaddrRegexp;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = "i.IP = ?";
            $args[] = $this->filterIpaddr;
        }
        $where  = implode(' AND ', $cond);
        $args[] = $limit;
        $args[] = $offset;
        self::$db->prepared_query("
            SELECT uhi.IP as ip_addr,
                count(DISTINCT UserID) as nr_users,
                group_concat(
                    concat(UserID, '/', StartTime, '/', coalesce(EndTime, now()))
                    ORDER BY if(UserID = ?, 0, 1), StartTime DESC
                ) AS ranges,
                min(uhi.StartTime) AS min_start,
                coalesce(max(uhi.EndTime), now()) AS max_end,
                exists (SELECT ib.ID FROM ip_bans ib WHERE inet_aton(uhi.IP) BETWEEN ib.FromIP AND ib.ToIP) AS is_banned
            FROM users_history_ips uhi
            WHERE IP IN (
                    SELECT DISTINCT i.IP
                    FROM users_history_ips i
                    WHERE $where
                )
            GROUP BY uhi.IP
            ORDER BY max_end DESC, ip_addr
            LIMIT ? OFFSET ?
            ", $user->id, ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }
}
