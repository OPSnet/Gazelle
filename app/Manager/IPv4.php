<?php

namespace Gazelle\Manager;

class IPv4 extends \Gazelle\Base {

    const CACHE_KEY = 'ipv4_bans_';

    protected $filterNotes;
    protected $filterIpaddr;

    public function setFilterNotes(string $filterNotes) {
        $this->filterNotes = $filterNotes;
        return $this;
    }

    public function setFilterIpaddr(string $filterIpaddr) {
        if (preg_match(IP_REGEXP, $filterIpaddr)) {
            $this->filterIpaddr = $filterIpaddr;
        }
        return $this;
    }

    public function queryBase(): array {
        $cond = [];
        $args = [];
        if (!is_null($this->filterNotes)) {
            $cond[] = "i.Reason REGEXP ?";
            $args[] = $this->filterNotes;
        }
        if (!is_null($this->filterIpaddr)) {
            $cond[] = "? BETWEEN inet_aton(i.FromIP) AND inet_aton(i.ToIP)";
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
        return $this->db->scalar("
            SELECT count(*) $from
            ", ...$args
        );
    }

    public function page(string $orderBy, string $orderDir, int $limit, int $offset): array {
        [$from, $args] = $this->queryBase();
        $this->db->prepared_query("
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
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Returns true if given IP is banned.
     * TODO: This looks really braindead. Why not compare the 32bit address
     *       directly BETWEEN FromIP AND ToIP? Apart from dubious merits of
     *       caching?
     *
     * @param string $IP
     * @return bool True if banned
     */
    public function isBanned(string $IP) {
        $A = substr($IP, 0, strcspn($IP, '.'));
        $key = self::CACHE_KEY . $A;
        $IPBans = $this->cache->get_value($key);
        if (!is_array($IPBans)) {
            $this->db->prepared_query("
                SELECT FromIP, ToIP, ID
                FROM ip_bans
                WHERE FromIP BETWEEN ? << 24 AND (? << 24) - 1
                ", $A, $A + 1
            );
            $IPBans = $this->db->to_array(0, MYSQLI_NUM, false);
            $this->cache->cache_value($key, $IPBans, 0);
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
     * the given reason to an existing ban.
     *
     * @param int $userId The person doing the band (0 for system)
     * @param string $from The first address (dotted quad a.b.c.d)
     * @param string $to The last adddress in the range (may equal $from)
     * @param string $reason Why ban?
     * @return record id
     */
    public function createBan(int $userId, string $from, string $to, string $reason): int {
        $id = $this->db->scalar("
            SELECT ID
            FROM ip_bans
            WHERE FromIP = inet_aton(?)
                AND ToIP = inet_aton(?)
            ", $from, $to
        );
        if ($id) {
            $this->db->prepared_query("
                UPDATE ip_bans SET
                    Reason  = concat(Reason, ' AND ', ?),
                    created = now()
                WHERE ID = ?
                ", trim($reason), $id
            );
            return $id;
        } else {
            $this->db->prepared_query("
                INSERT INTO ip_bans
                       (Reason, FromIP,       ToIP,         user_id)
                VALUES (?,      inet_aton(?), inet_aton(?), ?)
                ", $reason, $from, $to, $userId
            );
            $this->cache->delete_value(
                self::CACHE_KEY . substr($from, 0, strcspn($from, '.'))
            );
            return $this->db->inserted_id();
        }
    }

    /**
     * Modify an ip address ban over a range of addresses. Will append
     * the given reason to an existing ban.
     *
     * @param int userId The person doing the band (0 for system)
     * @param string from The first address (dotted quad a.b.c.d)
     * @param string to The last adddress in the range (may equal $from)
     * @param string reason Why ban?
     * @return bool succeeded
     */
    public function modifyBan(int $id, int $userId, string $from, string $to, string $reason): bool {
        $this->db->prepared_query("
            UPDATE ip_bans SET
                Reason  = ?,
                FromIP  = inet_aton(?),
                ToIP    = inet_aton(?),
                user_id = ?,
                created = now()
            WHERE ID = ?
            ", $reason, $from, $to, $userId, $id
        );
        $this->cache->delete_value(
            self::CACHE_KEY . substr($from, 0, strcspn($from, '.'))
        );
        return $this->db->affected_rows() === 1;
    }

    /**
     * Remove an ip ban
     *
     * param int $id Row to remove
     */
    public function removeBan(int $id): bool {
        $fromClassA = $this->db->scalar("
            SELECT FromIP >> 24 FROM ip_bans WHERE ID = ?
            ", $id
        );
        if (is_null($fromClassA)) {
            return false;
        }
        $this->db->prepared_query("
            DELETE FROM ip_bans WHERE ID = ?
            ", $id
        );
        if ($this->db->affected_rows()) {
            $this->cache->delete_value(self::CACHE_KEY . $fromClassA);
        }
        return $this->db->affected_rows() === 1;
    }
}
