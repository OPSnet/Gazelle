<?php

namespace Gazelle\User;

class History extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    public function __construct(
        \Gazelle\User $user,
        protected string $column = 'ip',
        protected string $direction = 'up'
    ) {
        parent::__construct($user);
    }

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    /**
     * Email history
     *
     * @return array [email address, ip, date, useragent, country code, AS, AS name]
     */
    public function email(\Gazelle\Search\ASN $asn): array {
        self::$db->prepared_query("
            SELECT h.Email  AS email,
                h.created   AS created,
                h.IP        AS ipv4,
                h.useragent AS useragent
            FROM users_history_emails AS h
            WHERE h.UserID = ?
            ORDER BY h.created DESC
            ", $this->id()
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
                IP     AS ipv4,
                created,
                useragent
            FROM users_history_emails AS uhe
            WHERE uhe.UserID != ?
                AND uhe.Email in (SELECT DISTINCT Email FROM users_history_emails WHERE UserID = ?)
            ORDER BY uhe.Email, uhe.created DESC
            ", $this->id(), $this->id()
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
     * How many email addresses have been recorded for this user
     */
    public function emailTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM users_history_emails WHERE UserID = ?
            ", $this->id()
        );
    }

    /**
     * Register the change in email address. Note: this should be called
     * *BEFORE* updating the new email address, otherwise it will not be
     * possible to send a warning message to the old address.
     */
    public function registerNewEmail(
        string $newEmail,
        bool $notify,
        \Gazelle\Manager\IPv4 $ipv4,
        \Gazelle\Util\Irc $irc,
        \Gazelle\Util\Mail $mailer
    ): int {
        $ipaddr = $this->requestContext()->remoteAddr();
        $useragent = $this->requestContext()->useragent();
        self::$db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?,  ?)
            ", $this->id(), $newEmail, $ipaddr, $useragent
        );
        $affected = self::$db->affected_rows();
        if ($notify) {
            $irc::sendMessage(
                $this->user->username(),
                "Security alert: Your email address was changed via $ipaddr with $useragent. Not you? Contact staff ASAP."
            );
            if ($ipv4->setFilterIpaddr($ipaddr)->userTotal($this->user) == 0) {
                $irc::sendMessage(
                    IRC_CHAN_STAFF,
                    "Email address for {$this->user->username()} was changed from {$this->user->email()} to $newEmail from unusual address $ipaddr with UA=$useragent."
                );
            }
            $mailer->send($this->user->email(), 'Email address changed information for ' . SITE_NAME,
                self::$twig->render('email/email-address-change.twig', [
                    'ipaddr'     => $ipaddr,
                    'new_email'  => $newEmail,
                    'now'        => date('Y-m-d H:i:s'),
                    'user_agent' => $useragent,
                    'username'   => $this->user->username(),
                ])
            );
        }
        return $affected;
    }

    public function resetEmail(string $email, string $ipaddr): int {
        self::$db->prepared_query("
            DELETE FROM users_history_emails
            WHERE UserID = ?
            ", $this->id()
        );
        self::$db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?, 'email-reset')
            ", $this->id(), $email, $ipaddr
        );
        self::$db->prepared_query("
            UPDATE users_main SET
                Email = ?
            WHERE ID = ?
            ", $email, $this->id()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function registerSiteIp(string $ipaddr, int $delay = IP_HISTORY_NEW_INTERVAL): int {
        // I had initially hoped that it would be possible to do this:
        //
        // insert into ip_site_history (id_user, ip) values (2, '2.2.2.2')
        // on conflict (id_user, ip) do update set
        //    total = EXCLUDED.total + 1,
        //    seen = EXCLUDED.seen
        //        + case when upper(EXCLUDED.seen) > now() - '1 day'::interval
        //        then tstzmultirange(tstzrange(upper(EXCLUDED.seen), now(), '[]'))
        //        else tstzmultirange(tstzrange(now(), now(), '[]'))
        //    end;
        //
        // but for some reason, total is never incremented beyond 2 and seen is
        // clobbered by the last tstzrange. Some day I will figure this out, in
        // the meantime, this roundabout technique will have to do – Spine

        $this->pg()->pdo()->beginTransaction();
        $recent = $this->pg()->scalar("
            select upper(seen) > now() - '1 second'::interval * ? as recent
            from ip_site_history
            where id_user = ?
                and ip = ?
            ", $delay, $this->id(), $ipaddr
        );
        $total = match ($recent) {
            true => $this->pg()->writeReturning("
                update ip_site_history set
                    seen = seen + tstzmultirange(tstzrange(upper(seen), now(), '[]')),
                    total = total + 1
                where id_user = ?
                and ip = ?
                returning total
                ", $this->id(), $ipaddr
            ),
            false => $this->pg()->writeReturning("
                update ip_site_history set
                    seen = seen + tstzmultirange(tstzrange(now(), now(), '[]')),
                    total = total + 1
                where id_user = ?
                and ip = ?
                returning total
                ", $this->id(), $ipaddr
            ),
            default => $this->pg()->writeReturning("
                insert into ip_site_history
                       (id_user, ip)
                values (?,       ?)
                returning total
                ", $this->id(), $ipaddr
            ),
        };
        $this->pg()->pdo()->commit();
        return (int)$total;
    }

    public function siteIPv4(\Gazelle\Search\ASN $asn): array {
        $dir = $this->direction === 'down' ? 'DESC' : 'ASC';
        $orderBy = match ($this->column) {
            'first' => "StartTime $dir, inet_aton(IP) $dir, EndTime $dir",
            'last'  => "EndTime $dir, inet_aton(IP) $dir, StartTime $dir",
            default => "inet_aton(IP) $dir, StartTime $dir, EndTime $dir",
        };
        self::$db->prepared_query("
            SELECT IP                         AS ipv4,
                min(StartTime)                AS first_seen,
                max(coalesce(EndTime, now())) AS last_seen
            FROM users_history_ips
            WHERE UserID = ?
            GROUP BY IP
            ORDER BY $orderBy
            ", $this->id()
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
        $orderBy = match ($this->column) {
            'first' => "from_unixtime(min(tstamp)) $dir, inet_aton(IP) $dir, from_unixtime(max(tstamp)) $dir",
            'last'  => "from_unixtime(max(tstamp)) $dir, inet_aton(IP) $dir, from_unixtime(min(tstamp)) $dir",
            default => "inet_aton(IP) $dir, from_unixtime(min(tstamp)) $dir, from_unixtime(max(tstamp)) $dir",
        };
        self::$db->prepared_query("
            SELECT IP                      AS ipv4,
                from_unixtime(min(tstamp)) AS first_seen,
                from_unixtime(max(tstamp)) AS last_seen
            FROM xbt_snatched
            WHERE uid = ?
            GROUP BY IP
            ORDER BY $orderBy
            ", $this->id()
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
        $n = $this->pg()->prepared_query("
            delete from ip_history where id_user = ?
            ", $this->id()
        );
        $n += $this->pg()->prepared_query("
            delete from ip_site_history where id_user = ?
            ", $this->id()
        );
        self::$db->prepared_query("
            DELETE FROM users_history_ips WHERE UserID = ?
            ", $this->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_main SET IP = '127.0.0.1' WHERE ID = ?
            ", $this->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE xbt_snatched SET IP = '' WHERE uid = ?
            ", $this->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_history_passwords SET ChangerIP = '', useragent = 'reset-ip-history' WHERE UserID = ?
            ", $this->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_history_passkeys SET ChangerIP = '' WHERE UserID = ?
            ", $this->id()
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_sessions SET IP = '127.0.0.1' WHERE UserID = ?
            ", $this->id()
        );
        $n += self::$db->affected_rows();
        $this->flush();
        return $n;
    }

    public function resetDownloaded(): int {
        self::$db->prepared_query('
            DELETE FROM users_downloads
            WHERE UserID = ?
            ', $this->id()
        );
        return self::$db->affected_rows();
    }

    public function resetRatioWatch(): int {
        self::$db->prepared_query("
            UPDATE users_info SET
                RatioWatchEnds = NULL,
                RatioWatchDownload = 0,
                RatioWatchTimes = 0
            WHERE UserID = ?
            ", $this->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function resetSnatched(): int {
        self::$db->prepared_query("
            DELETE FROM xbt_snatched
            WHERE uid = ?
            ", $this->id()
        );
        $this->user->snatch()->flush();
        return self::$db->affected_rows();
    }
}
