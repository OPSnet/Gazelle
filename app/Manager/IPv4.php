<?php

namespace Gazelle\Manager;

class IPv4 extends \Gazelle\Base {

    const CACHE_KEY = 'ipv4_bans_';

    /**
     * Returns the unsigned 32bit form of an IPv4 address
     *
     * @param string $ipv4 The IP address x.x.x.x
     * @return string the long it represents.
     */
    public function ip2ulong(string $ipv4) {
        return sprintf('%u', ip2long($ipv4));
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
        $key = self::CACHE_KEY . substr($IP, 0, strcspn($IP, '.'));
        $IPBans = $this->cache->get_value($key);
        if (!is_array($IPBans)) {
            $this->db->prepared_query("
                SELECT FromIP, ToIP, ID
                FROM ip_bans
                WHERE FromIP BETWEEN ? << 24 AND (? << 24) - 1
                ", $A, $A + 1
            );
            $IPBans = $this->db->to_array(0, MYSQLI_NUM);
            $this->cache->cache_value($key, $IPBans, 0);
        }
        $IPNum = $this->ip2ulong($IP);
        foreach ($IPBans as $Index => $IPBan) {
            list ($FromIP, $ToIP) = $IPBan;
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
     */
    public function createBan(int $userId,  $ipv4From, string $ipv4To, string $reason) {
        $from = $this->ip2ulong($ipv4From);
        $to   = $this->ip2ulong($ipv4To);
        $current = $this->db->scalar('
            SELECT Reason
            FROM ip_bans
            WHERE ? BETWEEN FromIP AND ToIP
            ', $from
        );
        if ($current) {
            if ($current != $reason) {
                $this->db->prepared_query("
                    UPDATE ip_bans SET
                        Reason = concat(?, ' AND ', Reason),
                        user_id = ?,
                        created = now()
                    WHERE FromIP = ?
                        AND ToIP = ?
                    ", $reason, $userId, $from, $to
                );
            }
        } else { // Not yet banned
            $this->db->prepared_query("
                INSERT INTO ip_bans
                       (Reason, FromIP, ToIP, user_id)
                VALUES (?,      ?,      ?,    ?)
                ", $reason, $from, $to, $userId
            );
            $this->cache->delete_value(
                self::CACHE_KEY . substr($ipaddr, 0, strcspn($ipaddr, '.')),
            );
        }
    }

    /**
     * Remove an ip ban
     *
     * param int $id Row to remove
     */
    public function removeBan(int $id) {
        $fromClassA = $this->db->scalar("
            SELECT FromIP >> 24 FROM ip_bans WHERE ID = ?
            ", $id
        );
        if (is_null($fromClassA)) {
            return;
        }
        $this->db->prepared_query("
            DELETE FROM ip_bans WHERE ID = ?
            ", $id
        );
        if ($this->db->affected_rows()) {
            $this->cache->delete_value(self::CACHE_KEY . $fromClassA);
        }
    }
}
