<?php

namespace Gazelle\User;

use Gazelle\UserCreator;
use Gazelle\Search\ASN;
use Gazelle\Util\Irc;
use Gazelle\Util\Mail;

/**
 * This class deals with the storage of previous announce keys, email address
 * and passwords.
 */

class History extends \Gazelle\BaseUser {
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

    /***** ANNOUNCE KEYS *****/

    public function announceKeyTotal(): int {
        return (int)$this->pg()->scalarex("
            select count(*) from history_announce where id_user = $1
            ", $this->user->id
        );
    }

    public function announceKeyList(): array {
        return $this->pg()->all("
            SELECT previous,
                created,
                ip,
                useragent
            from history_announce
            where id_user = ?
            order by created desc
            ", $this->user->id
        );
    }

    /**
     * Set a new user announce key. Requires calling User::modify() to persist change.
     */
    public function modifyAnnounceKey(string $old, string $new): int {
        $this->user->setField('torrent_pass', $new);
        $id = $this->pg()->insert("
            insert into history_announce
                   (id_user, previous, ip, useragent)
            values (?,      ?,          ?,          ?)
            ", $this->user->id, $old,
                $this->requestContext()->remoteAddr(),
                $this->requestContext()->useragent(),
        );
        self::$cache->delete_value("user_passkey_count_{$this->user->id}");
        return $id;
    }

    /***** PASSWORDS *****/

    public function passwordAge(): int {
        return (int)$this->user->getSinglePgValue('user_pw_epoch', "
            select extract(epoch from now())
                - extract(epoch from coalesce(max(hp.created), um.created))
            from relay.users_main um
            left join history_password hp on (hp.id_user = um.\"ID\")
            where um.\"ID\" = ?
            group by um.created
        ");
    }

    public function passwordList(): array {
        return $this->pg()->allex("
            select created,
                ip,
                useragent
            from history_password
            where id_user = $1
            order by created desc
            ", $this->user->id
        );
    }

    public function passwordTotal(): int {
        return (int)$this->pg()->scalarex("
            select count(*) from history_password where id_user = $1
            ", $this->user->id
        );
    }

    /**
     * Set a new user password. Requires calling User::modify() to persist.
     */
    public function modifyPassword(
        #[\SensitiveParameter] string $new,
        bool $notify,
        Mail $mail = new Mail(),
    ): static {
        $this->user->setField('PassHash', UserCreator::hashPassword($new));
        $ipaddr    = $this->requestContext()->remoteAddr();
        $useragent = $this->requestContext()->useragent();
        $this->pg()->insert("
            insert into history_password
                   (id_user, ip,      useragent)
            VALUES (?,       ?::inet, ?)
            ", $this->user->id, $ipaddr, $useragent
        );
        self::$cache->delete_value("user_pw_count_{$this->user->id}");
        if ($notify) {
            Irc::sendMessage(
                $this->user->username(),
                "Security alert: Your password was changed via $ipaddr with $useragent"
            );
            $mail->send(
                $this->user->email(),
                'Password changed information for ' . SITE_NAME,
                self::$twig->render('email/password-change.twig', [
                    'ipaddr'    => $ipaddr,
                    'useragent' => $useragent,
                    'username'  => $this->user->username(),
                ])
            );
        }
        return $this;
    }

    /***** EMAIL ADDRESSES *****/

    /**
     * Email history
     *
     * @return array [email address, ip, date, useragent, country code, AS, AS name]
     */
    public function email(ASN $asn = new ASN()): array {
        self::$db->prepared_query("
            select h.Email  AS email,
                h.created   AS created,
                h.IP        AS ip,
                h.useragent AS useragent
            FROM users_history_emails AS h
            WHERE h.UserID = ?
            ORDER BY users_history_emails_id DESC
            ", $this->user->id
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC);
        static::addAsnInfo($asn, $list);
        return $list;
    }

    /**
     * Email duplicates
     *
     * @return array of array of [id, email, user_id, created, ip, \User user]
     */
    public function emailDuplicate(ASN $asn = new ASN()): array {
        // Get history of matches
        self::$db->prepared_query("
            SELECT users_history_emails_id AS id,
                Email  AS email,
                UserID AS user_id,
                IP     AS ip,
                created,
                useragent
            FROM users_history_emails AS uhe
            WHERE uhe.UserID != ?
                AND uhe.Email in (SELECT DISTINCT Email FROM users_history_emails WHERE UserID = ?)
            ORDER BY uhe.Email, uhe.created DESC
            ", $this->user->id, $this->user->id
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC);
        static::addAsnInfo($asn, $list);
        return $list;
    }

    /**
     * How many email addresses have been recorded for this user
     */
    public function emailTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM users_history_emails WHERE UserID = ?
            ", $this->user->id
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
        \Gazelle\Manager\IPv4 $ipv4   = new \Gazelle\Manager\IPv4(),
        \Gazelle\Util\Irc     $irc    = new \Gazelle\Util\Irc(),
        \Gazelle\Util\Mail    $mailer = new \Gazelle\Util\Mail(),
    ): int {
        $ipaddr = $this->requestContext()->remoteAddr();
        $useragent = $this->requestContext()->useragent();
        self::$db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?,  ?)
            ", $this->user->id, $newEmail, $ipaddr, $useragent
        );
        $affected = self::$db->affected_rows();
        $this->pg()->insert("
            insert into history_email
                   (id_user, email, useragent, ip)
            values (?,       ?,     ?,         ?::inet)
            ", $this->id, $newEmail, $useragent, $ipaddr
        );
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
            ", $this->user->id
        );
        self::$db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?, 'email-reset')
            ", $this->user->id, $email, $ipaddr
        );
        self::$db->prepared_query("
            UPDATE users_main SET
                Email = ?
            WHERE ID = ?
            ", $email, $this->user->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function registerSiteIp(string $ipaddr, int $delay = IP_HISTORY_NEW_INTERVAL): int {
        return (int)$this->pg()->writeReturning("
            with cur as (
                select ?::int id_user,
                    ?::inet as ip,
                    total,
                    upper(seen) as recent
                from ip_site_history
                where id_user = ?
                    and ip = ?::inet
            ),
            ins as (
                select
                    coalesce((select id_user from cur), ?)   as id_user,
                    coalesce((select ip from cur), ?::inet)  as ip,
                    coalesce((select total + 1 from cur), 1) as total,
                    tstzmultirange(
                        tstzrange(
                            case when (select recent from cur) > now() - '1 second'::interval * ?
                                then (select recent from cur)
                                else now()
                            end,
                            now(),
                            '[]'
                        )
                    ) as seen
            )
            insert into ip_site_history as s
                  (id_user, ip, total, seen)
            select id_user, ip, total, seen from ins
            on conflict (id_user, ip) do update set
                total = EXCLUDED.total,
                seen  = s.seen + EXCLUDED.seen
            returning total
            ", $this->user->id, $ipaddr, $this->user->id, $ipaddr, $this->user->id, $ipaddr, $delay
        );
    }

    public function apiIP(ASN $asn = new ASN()): array {
        $dir = $this->direction === 'down' ? 'desc' : 'asc';
        $orderBy = match ($this->column) {
            'first' => "first_seen $dir, ip $dir, last_seen $dir",
            'last'  => "last_seen $dir, ip $dir, first_seen $dir",
            default => "ip $dir, first_seen $dir, last_seen $dir",
        };
        $result = $this->pg()->executeParams("
            select ip       ip,
                lower(seen) first_seen,
                upper(seen) last_seen
            from ip_history
            where id_user = $1
                and data_origin = 'token'
            order by $orderBy
            ", $this->user->id
        )->fetchAll();
        static::addAsnInfo($asn, $result);
        return $result;
    }

    public function siteIP(ASN $asn = new ASN()): array {
        $dir = $this->direction === 'down' ? 'desc' : 'asc';
        $orderBy = match ($this->column) {
            'first' => "first_seen $dir, ip $dir, last_seen $dir",
            'last'  => "last_seen $dir, ip $dir, first_seen $dir",
            default => "ip $dir, first_seen $dir, last_seen $dir",
        };
        $result = $this->pg()->executeParams("
            select ip               ip,
                lower(unnest(seen)) first_seen,
                upper(unnest(seen)) last_seen
            from ip_site_history
            where id_user = $1
            order by $orderBy
            ", $this->user->id
        )->fetchAll();
        static::addAsnInfo($asn, $result);
        return $result;
    }

    public function trackerIP(ASN $asn = new ASN()): array {
        $dir = $this->direction === 'down' ? 'DESC' : 'ASC';
        $orderBy = match ($this->column) {
            'first' => "from_unixtime(min(tstamp)) $dir, inet_aton(IP) $dir, from_unixtime(max(tstamp)) $dir",
            'last'  => "from_unixtime(max(tstamp)) $dir, inet_aton(IP) $dir, from_unixtime(min(tstamp)) $dir",
            default => "inet_aton(IP) $dir, from_unixtime(min(tstamp)) $dir, from_unixtime(max(tstamp)) $dir",
        };
        self::$db->prepared_query("
            SELECT IP                      AS ip,
                from_unixtime(min(tstamp)) AS first_seen,
                from_unixtime(max(tstamp)) AS last_seen
            FROM xbt_snatched
            WHERE uid = ?
            GROUP BY IP
            ORDER BY $orderBy
            ", $this->user->id
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC);
        foreach ($list as &$row) {
            $row['first_seen'] = new \DateTimeImmutable($row['first_seen']);
            $row['last_seen']  = new \DateTimeImmutable($row['last_seen']);
        }
        static::addAsnInfo($asn, $list);
        return $list;
    }

    public function resetIp(): int {
        $n = $this->pg()->prepared_query("
            delete from ip_history where id_user = ?
            ", $this->user->id
        );
        $n += $this->pg()->prepared_query("
            delete from ip_site_history where id_user = ?
            ", $this->user->id
        );
        self::$db->prepared_query("
            DELETE FROM users_history_ips WHERE UserID = ?
            ", $this->user->id
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE users_main SET IP = '127.0.0.1' WHERE ID = ?
            ", $this->user->id
        );
        $n += self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE xbt_snatched SET IP = '' WHERE uid = ?
            ", $this->user->id
        );
        $n += self::$db->affected_rows();
        $n += $this->pg()->prepared_query("
            update history_password set
                ip = '0.0.0.0',
                useragent = 'reset-ip-history'
            where id_user = ?
            ", $this->user->id
        );
        $n += $this->pg()->prepared_query("
            update history_announce set
                ip = '0.0.0.0',
                useragent = 'reset-ip-history'
            where id_user = ?
            ", $this->user->id
        );
        self::$db->prepared_query("
            UPDATE users_sessions SET IP = '127.0.0.1' WHERE UserID = ?
            ", $this->user->id
        );
        $n += self::$db->affected_rows();
        $this->flush();
        return $n;
    }

    public function resetDownloaded(): int {
        self::$db->prepared_query('
            DELETE FROM users_downloads WHERE UserID = ?
            ', $this->user->id
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
            ", $this->user->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function resetSnatched(): int {
        self::$db->prepared_query("
            DELETE FROM xbt_snatched WHERE uid = ?
            ", $this->user->id
        );
        $this->user->snatch()->flush();
        return self::$db->affected_rows();
    }

    protected static function addAsnInfo(ASN $asn, array &$ipList): void {
        $asnList = $asn->findByIpList(array_unique(array_map(fn ($r) => $r['ip'], $ipList)));
        foreach ($ipList as &$row) {
            $row['cc']   = $asnList[$row['ip']]['cc'];
            $row['n']    = $asnList[$row['ip']]['n'];
            $row['name'] = $asnList[$row['ip']]['name'];
        }
    }
}
