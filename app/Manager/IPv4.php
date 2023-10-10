<?php

namespace Gazelle\Manager;

class IPv4 extends \Gazelle\Base {
    final const CACHE_KEY = 'ipv4_bans_';

    protected string $filterNotes;
    protected string $filterIpaddr;
    protected string $filterIpaddrRegexp;

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
            "FROM ip_bans i LEFT JOIN users_main um ON (um.ID = i.user_id)"
                . (empty($cond) ? '' : (' WHERE ' . implode(' AND ', $cond))),
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
                um.Username         AS username
            $from
            ORDER BY $orderBy $orderDir
            LIMIT ? OFFSET ?
            ", ...array_merge($args, [$limit, $offset])
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function userTotal(int $userId): int {
        $cond = ['UserID = ?'];
        $args = [$userId];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = "IP REGEXP ?";
            $args[] = $this->filterIpaddrRegexp;
        }
        $where  = join(' AND ', $cond);
        return (int)self::$db->scalar("
            SELECT count(DISTINCT IP) FROM users_history_ips WHERE $where
            ", ...$args
        );
    }

    public function userPage(int $userId, int $limit, int $offset): array {
        self::$db->prepared_query("SET SESSION group_concat_max_len = 50000");
        $cond = ['i.UserID = ?'];
        $args = [$userId];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = "i.IP REGEXP ?";
            $args[] = $this->filterIpaddrRegexp;
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
            ", $userId, ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Is an IP address banned?
     * TODO: This looks really braindead. Why not compare the 32bit address
     *       directly BETWEEN FromIP AND ToIP? Apart from dubious merits of
     *       caching?
     */
    public function isBanned(string $IP): bool {
        $A = substr($IP, 0, strcspn($IP, '.'));
        $key = self::CACHE_KEY . $A;
        $IPBans = self::$cache->get_value($key);
        if (!is_array($IPBans)) {
            self::$db->prepared_query("
                SELECT FromIP, ToIP, ID
                FROM ip_bans
                WHERE FromIP BETWEEN ? << 24 AND (? << 24) + 1
                ", $A, $A
            );
            $IPBans = self::$db->to_array(false, MYSQLI_NUM, false);
            self::$cache->cache_value($key, $IPBans, 0);
        }
        $IPNum = sprintf('%u', ip2long($IP));
        foreach ($IPBans as $IPBan) {
            [$FromIP, $ToIP] = $IPBan;
            if ($IPNum >= $FromIP && $IPNum <= $ToIP) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create an ip address ban over a range of addresses. Will append
     * the given reason to an existing ban. $to and $from are dotted quads
     */
    public function createBan(int $userId, string $from, string $to, string $reason): int {
        $id = (int)self::$db->scalar("
            SELECT ID
            FROM ip_bans
            WHERE FromIP = inet_aton(?)
                AND ToIP = inet_aton(?)
            ", $from, $to
        );
        if ($id) {
            self::$db->prepared_query("
                UPDATE ip_bans SET
                    Reason  = substring(concat(Reason, ' AND ', ?), 1, 255),
                    created = now()
                WHERE ID = ?
                ", trim($reason), $id
            );
            return $id;
        }
        self::$db->prepared_query("
            INSERT INTO ip_bans
                   (Reason, FromIP,       ToIP,         user_id)
            VALUES (?,      inet_aton(?), inet_aton(?), ?)
            ", substr($reason, 1, 255), $from, $to, $userId
        );
        self::$cache->delete_value(
            self::CACHE_KEY . substr($from, 0, strcspn($from, '.'))
        );
        return self::$db->inserted_id();
    }

    /**
     * Modify an ip address ban over a range of addresses. Will append
     * the given reason to an existing ban. $to and $from are dotted quads.
     */
    public function modifyBan(int $id, int $userId, string $from, string $to, string $reason): bool {
        self::$db->prepared_query("
            UPDATE ip_bans SET
                Reason  = ?,
                FromIP  = inet_aton(?),
                ToIP    = inet_aton(?),
                user_id = ?,
                created = now()
            WHERE ID = ?
            ", substr($reason, 1, 255), $from, $to, $userId, $id
        );
        self::$cache->delete_value(
            self::CACHE_KEY . substr($from, 0, strcspn($from, '.'))
        );
        return self::$db->affected_rows() === 1;
    }

    /**
     * Remove the record of an ip ban
     */
    public function removeBan(int $id): bool {
        $fromClassA = self::$db->scalar("
            SELECT FromIP >> 24 FROM ip_bans WHERE ID = ?
            ", $id
        );
        if (is_null($fromClassA)) {
            return false;
        }
        self::$db->prepared_query("
            DELETE FROM ip_bans WHERE ID = ?
            ", $id
        );
        if (self::$db->affected_rows()) {
            self::$cache->delete_value(self::CACHE_KEY . $fromClassA);
        }
        return self::$db->affected_rows() === 1;
    }
}
