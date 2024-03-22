<?php

namespace Gazelle\Manager;

/**
 * This class handles both the user IP site history as well as
 * the bans placed upon IP addresses.
 */

class IPv4 extends \Gazelle\Base {
    use \Gazelle\Pg;

    final protected const CACHE_KEY = 'ipv4_bans_';

    protected string $filterNotes;
    protected string $filterIpaddr;
    protected string $filterIpaddrRegexp;

    public function flush(): static {
        foreach (range(1, 223) as $n) {
            self::$cache->delete_value(self::CACHE_KEY . $n);
        }
        // reset the internal state to allow a new search
        unset($this->filterNotes);
        unset($this->filterIpaddr);
        unset($this->filterIpaddrRegexp);
        return $this;
    }

    public function register(\Gazelle\User $user, string $ipv4): int {
        $this->pg()->prepared_query("
            insert into ip_history
                   (id_user, ip, data_origin)
            values (?,       ?,  'site')
            on conflict (id_user, ip, data_origin) do update set
                total = ip_history.total + 1,
                seen = tstzrange(lower(ip_history.seen), now())
            ", $user->id(), $ipv4
        );
        self::$db->prepared_query('
            INSERT INTO users_history_ips
                   (UserID, IP)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE EndTime = now()
            ', $user->id(), $ipv4
        );
        $affected = self::$db->affected_rows();
        $user->setField('IP', $ipv4)
            ->setField('ipcc', geoip($ipv4))
            ->modify();
        self::$cache->delete_value(sprintf('ipv4_dup_' . str_replace('-', '_', $ipv4)));
        $this->flush();
        return $affected;
    }

    public function duplicateTotal(\Gazelle\User $user): int {
        $cacheKey = "ipv4_dup_" . str_replace('-', '_', $user->ipaddr());
        $value = self::$cache->get_value($cacheKey);
        if ($value === false) {
            $value = (int)self::$db->scalar("
                SELECT count(*) FROM users_history_ips WHERE IP = ?
                ", $user->ipaddr()
            );
            self::$cache->cache_value($cacheKey, $value, 3600);
        }
        return max(0, (int)$value - 1);
    }

    public function setFilterNotes(string $filterNotes): static {
        $this->filterNotes = $filterNotes;
        return $this;
    }

    public function setFilterIpaddr(string $filterIpaddr): static {
        if (preg_match(IP_REGEXP, $filterIpaddr)) {
            $this->filterIpaddr = $filterIpaddr;
        }
        return $this;
    }

    public function setFilterIpaddrRegexp(string $re): static {
        $this->filterIpaddrRegexp = $re;
        return $this;
    }

    public function queryBase(): array {
        $cond = [];
        $args = [];
        if (isset($this->filterNotes)) {
            $cond[] = "i.Reason REGEXP ?";
            $args[] = $this->filterNotes;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = "inet_aton(?) BETWEEN i.FromIP AND i.ToIP";
            $args[] = $this->filterIpaddr;
        }
        return [
            "FROM ip_bans i" . (empty($cond) ? '' : (' WHERE ' . implode(' AND ', $cond))),
            $args
        ];
    }

    public function total(): int {
        [$from, $args] = $this->queryBase();
        return (int)self::$db->scalar("
            SELECT count(*) $from
            ", ...$args
        );
    }

    public function page(string $orderBy, string $orderDir, int $limit, int $offset): array {
        [$from, $args] = $this->queryBase();
        self::$db->prepared_query("
            SELECT i.ID             AS id,
                inet_ntoa(i.FromIP) AS from_ip,
                inet_ntoa(i.ToIP)   AS to_ip,
                i.Reason            AS reason,
                i.user_id,
                i.created           AS created,
                (SELECT Username FROM users_main um WHERE um.ID = i.user_id) AS Username
            $from
            ORDER BY $orderBy $orderDir
            LIMIT ? OFFSET ?
            ", ...array_merge($args, [$limit, $offset])
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function userTotal(\Gazelle\User $user): int {
        $cond = ['uhi.UserID = ?'];
        $args = [$user->id()];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = "uhi.IP REGEXP ?";
            $args[] = $this->filterIpaddrRegexp;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = "uhi.IP = ?";
            $args[] = $this->filterIpaddr;
        }
        $where  = join(' AND ', $cond);
        return (int)self::$db->scalar("
            SELECT count(DISTINCT IP) FROM users_history_ips uhi WHERE $where
            ", ...$args
        );
    }

    public function userPage(\Gazelle\User $user, int $limit, int $offset): array {
        self::$db->prepared_query("SET SESSION group_concat_max_len = 50000");
        $cond = ['i.UserID = ?'];
        $args = [$user->id()];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = "i.IP REGEXP ?";
            $args[] = $this->filterIpaddrRegexp;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = "i.IP = ?";
            $args[] = $this->filterIpaddr;
        }
        $where  = join(' AND ', $cond);
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
            ", $user->id(), ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Create an ip address ban over a range of addresses. Will append
     * the given reason to an existing ban. $to and $from are dotted quads
     * Cannot pass in a user object because someone may be trying to
     * force entry with an ID that does not correspond to a user, e.g. due
     * to a password brute-forcing attempt.
     */
    public function createBan(?\Gazelle\User $user, string $from, string $to, string $reason): int {
        $id = (int)self::$db->scalar("
            SELECT ID
            FROM ip_bans
            WHERE FromIP = inet_aton(?)
                AND ToIP = inet_aton(?)
            ", $from, $to
        );
        $reason = trim($reason);
        if ($id) {
            self::$db->prepared_query("
                UPDATE ip_bans SET
                    Reason  = substring(concat(Reason, ' AND ', ?), 1, 255),
                    created = now()
                WHERE ID = ?
                ", $reason, $id
            );
        } else {
            self::$db->prepared_query("
                INSERT INTO ip_bans
                       (Reason, FromIP,       ToIP,         user_id)
                VALUES (?,      inet_aton(?), inet_aton(?), ?)
                ", substr($reason, 0, 255), $from, $to, (int)($user?->id())
            );
            $id = self::$db->inserted_id();
        }
        $this->flush();
        return $id;
    }

    /**
     * Is an IP address banned?
     * TODO: This looks really braindead. Why not compare the 32bit address
     *       directly BETWEEN FromIP AND ToIP? Apart from dubious merits of
     *       caching?
     */
    public function isBanned(string $IP): bool {
        $A = (int)substr($IP, 0, strcspn($IP, '.'));
        $key = self::CACHE_KEY . $A;
        $banList = self::$cache->get_value($key);
        if ($banList === false) {
            self::$db->prepared_query("
                SELECT FromIP, ToIP, ID
                FROM ip_bans
                WHERE FromIP BETWEEN ? << 24 AND (? + 1 << 24) - 1
                ", $A, $A
            );
            $banList = self::$db->to_array(false, MYSQLI_NUM, false);
            if ($banList) {
                self::$cache->cache_value($key, $banList, 0);
            }
        }
        $IPNum = sprintf('%u', ip2long($IP));
        foreach ($banList as $IPBan) {
            [$FromIP, $ToIP] = $IPBan;
            if ($IPNum >= $FromIP && $IPNum <= $ToIP) {
                return true;
            }
        }
        return false;
    }

    /**
     * Modify an ip address ban over a range of addresses. Will append
     * the given reason to an existing ban. $to and $from are dotted quads.
     */
    public function modifyBan(\Gazelle\User $user, int $id, string $from, string $to, string $reason): int {
        self::$db->prepared_query("
            UPDATE ip_bans SET
                Reason  = ?,
                FromIP  = inet_aton(?),
                ToIP    = inet_aton(?),
                user_id = ?,
                created = now()
            WHERE ID = ?
            ", substr($reason, 0, 255), $from, $to, $user->id(), $id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    /**
     * Remove the record of an ip ban
     */
    public function removeBan(int $id): int {
        $A = self::$db->scalar("
            SELECT FromIP >> 24 FROM ip_bans WHERE ID = ?
            ", $id
        );
        if (is_null($A)) {
            return 0;
        }
        self::$db->prepared_query("
            DELETE FROM ip_bans WHERE ID = ?
            ", $id
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value(self::CACHE_KEY . $A);
        return $affected;
    }
}
