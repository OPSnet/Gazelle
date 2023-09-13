<?php

namespace Gazelle\User;

class History extends \Gazelle\BaseUser {
    final const tableName = 'pm_conversations_users';

    public function __construct(
        \Gazelle\User $user,
        protected string $column = 'ip',
        protected string $direction = 'up'
    ) {
        parent::__construct($user);
    }

    public function flush(): History { $this->user()->flush(); return $this; }

    /**
     * Email history
     *
     * @return array [email address, ip, date, useragent]
     */
    public function email(\Gazelle\Search\ASN $asn): array {
        self::$db->prepared_query("
            SELECT h.Email  AS email,
                h.Time      AS created,
                h.IP        AS ipv4,
                h.useragent AS useragent
            FROM users_history_emails AS h
            WHERE h.UserID = ?
            ORDER BY h.Time DESC
            ", $this->user->id()
        );
        $asnList = $asn->findByIpList(self::$db->collect('ipv4', false));
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['cc']   = $asnList[$row['ipv4']]['cc'];
            $row['n']    = $asnList[$row['ipv4']]['n'];
            $row['name'] = $asnList[$row['ipv4']]['name'];
        }
        unset($row);
        return $list;
    }

    /**
     * Email duplicates
     *
     * @return array of array of [id, email, user_id, created, ipv4, \User user]
     */
    public function emailDuplicate(\Gazelle\Search\ASN $asn): array {
        // Get history of matches
        self::$db->prepared_query("
            SELECT users_history_emails_id AS id,
                Email  AS email,
                UserID AS user_id,
                Time   AS created,
                IP     AS ipv4,
                useragent
            FROM users_history_emails AS uhe
            WHERE uhe.UserID != ?
                AND uhe.Email in (SELECT DISTINCT Email FROM users_history_emails WHERE UserID = ?)
            ORDER BY uhe.Email, uhe.Time DESC
            ", $this->user->id(), $this->user->id()
        );
        $asnList = $asn->findByIpList(self::$db->collect('ipv4', false));
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['cc']   = $asnList[$row['ipv4']]['cc'];
            $row['n']    = $asnList[$row['ipv4']]['n'];
            $row['name'] = $asnList[$row['ipv4']]['name'];
        }
        unset($row);
        return $list;
    }

    public function siteIPv4(\Gazelle\Search\ASN $asn): array {
        $dir = $this->direction === 'down' ? 'DESC' : 'ASC';
        $orderBy = match($this->column) {
            'ip'    => "inet_aton(IP) $dir, StartTime $dir, EndTime $dir",
            'first' => "StartTime $dir, inet_aton(IP) $dir, EndTime $dir",
            'last'  => "EndTime $dir, inet_aton(IP) $dir, StartTime $dir",
        };
        self::$db->prepared_query("
            SELECT IP                         AS ipv4,
                min(StartTime)                AS first_seen,
                max(coalesce(EndTime, now())) AS last_seen
            FROM users_history_ips
            WHERE UserID = ?
            GROUP BY IP
            ORDER BY $orderBy
            ", $this->user->id()
        );
        $asnList = $asn->findByIpList(self::$db->collect('ipv4', false));
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['cc']   = $asnList[$row['ipv4']]['cc'];
            $row['n']    = $asnList[$row['ipv4']]['n'];
            $row['name'] = $asnList[$row['ipv4']]['name'];
        }
        unset($row);
        return $list;
    }

    public function trackerIPv4(\Gazelle\Search\ASN $asn): array {
        $dir = $this->direction === 'down' ? 'DESC' : 'ASC';
        $orderBy = match($this->column) {
            'ip'    => "IP $dir, from_unixtime(min(tstamp)) $dir, from_unixtime(max(tstamp)) $dir",
            'first' => "from_unixtime(min(tstamp)) $dir, IP $dir, from_unixtime(max(tstamp)) $dir",
            'last'  => "from_unixtime(max(tstamp)) $dir, IP $dir, from_unixtime(min(tstamp)) $dir",
        };
        self::$db->prepared_query("
            SELECT IP                      AS ipv4,
                from_unixtime(min(tstamp)) AS first_seen,
                from_unixtime(max(tstamp)) AS last_seen
            FROM xbt_snatched
            WHERE uid = ?
            GROUP BY inet_aton(IP)
            ORDER BY $orderBy
            ", $this->user->id()
        );
        $asnList = $asn->findByIpList(self::$db->collect('ipv4', false));
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['cc']   = $asnList[$row['ipv4']]['cc'];
            $row['n']    = $asnList[$row['ipv4']]['n'];
            $row['name'] = $asnList[$row['ipv4']]['name'];
        }
        unset($row);
        return $list;
    }

    public function resetIp(): int {
        $n = 0;
        self::$db->prepared_query("
            DELETE FROM users_history_ips WHERE UserID = ?
            ", $this->user->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_main SET IP = '127.0.0.1' WHERE ID = ?
            ", $this->user->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE xbt_snatched SET IP = '' WHERE uid = ?
            ", $this->user->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_history_passwords SET ChangerIP = '', useragent = 'reset-ip-history' WHERE UserID = ?
            ", $this->user->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_history_passkeys SET ChangerIP = '' WHERE UserID = ?
            ", $this->user->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_sessions SET IP = '127.0.0.1' WHERE UserID = ?
            ", $this->user->id()
        );
        $n += self::$db->affected_rows();
        $this->user->flush();
        return $n;
    }

    public function resetDownloaded(): int {
        self::$db->prepared_query('
            DELETE FROM users_downloads
            WHERE UserID = ?
            ', $this->user->id()
        );
        return self::$db->affected_rows();
    }

    public function resetEmail(string $email, string $ipaddr): bool {
        self::$db->prepared_query("
            DELETE FROM users_history_emails
            WHERE UserID = ?
            ", $this->user->id()
        );
        self::$db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?, 'email-reset')
            ", $this->user->id(), $email, $ipaddr
        );
        self::$db->prepared_query("
            UPDATE users_main
            SET Email = ?
            WHERE ID = ?
            ", $email, $this->user->id()
        );
        $this->user->flush();
        return self::$db->affected_rows() === 1;
    }

    public function resetRatioWatch(): bool {
        self::$db->prepared_query("
            UPDATE users_info SET
                RatioWatchEnds = NULL,
                RatioWatchDownload = 0,
                RatioWatchTimes = 0
            WHERE UserID = ?
            ", $this->user->id()
        );
        $this->user->flush();
        return self::$db->affected_rows() === 1;
    }

    public function resetSnatched(): int {
        self::$db->prepared_query("
            DELETE FROM xbt_snatched
            WHERE uid = ?
            ", $this->user->id()
        );
        $this->user->flushRecentSnatch();
        return self::$db->affected_rows();
    }
}
