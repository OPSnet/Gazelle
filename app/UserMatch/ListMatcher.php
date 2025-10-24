<?php

namespace Gazelle\UserMatch;

use Gazelle\Enum\Direction;
use Gazelle\Enum\UserMatchSort;


class ListMatcher extends \Gazelle\Base {
    final protected const string CACHE_KEY = 'listmatcher_cache_%s_%s';
    final protected const int MAX_INSERT = 1000;
    final protected const string DATE_REGEXP = '\d{4}-[01]\d-[0-3]\d(?:[T ][0-2]\d:[0-5]\d(?::[0-5]\d)?)?(?:\.\d+)?(?:[+-][0-2]\d:[0-5]\d|Z)?';

    protected string $ipTable;

    public function __construct(
        public readonly UserMatchSort $sortKey,
        public readonly Direction $sortDirection
    ) {}

    public function create(): static {
        $this->ipTable = 'tmp_bulksearch_ip_' . str_replace(['.', ' '], '', microtime());
        $this->pg()->prepared_query("drop table if exists " . $this->ipTable);
        $this->pg()->prepared_query("
            create temporary table {$this->ipTable} (
                addr inet primary key
            )
        ");
        self::$db->dropTemporaryTable($this->ipTable);
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE {$this->ipTable} (
                addr_n integer unsigned NOT NULL PRIMARY KEY,
                addr_a varchar(15) CHARACTER SET ASCII NOT NULL,
                KEY(addr_a)
            )
        ");
        return $this;
    }

    public static function extract(string $text): array {
        $ips = [];
        foreach (explode("\n", $text) as $line) {
            // do not match IPs followed by a dot to filter out common rDNS hostnames
            preg_match_all('/(' . IP_REGEXP_STEM . ')\b(?:$|[^\.])/', $line, $match);
            $uniqueMatches = array_unique($match[1]);
            if (count($uniqueMatches) > 1) {  // multiple ips in one line
                foreach ($uniqueMatches as $ip) {
                    $ips[] = [$ip, null, null];
                }
            } elseif (count($uniqueMatches) === 1) {  // single ip; try to find dates, too
                $ip = $match[1][0];
                preg_match('/(' . static::DATE_REGEXP . ')(?:.+?(' . static::DATE_REGEXP . '))?/', $line, $match);
                $dates = [];
                if (isset($match[0])) {
                    foreach ([1, 2] as $i) {
                        if (isset($match[$i])) {
                            try {
                                $dates[] = new \DateTimeImmutable($match[$i]);
                            } catch (\DateException) {
                                continue;
                            }
                        }
                    }
                }
                $ips[] = [$ip, $dates[0] ?? null, $dates[1] ?? null];
            }
        }
        return $ips;
    }

    public function addIps(array $ips): int {
        $added = 0;
        foreach (array_chunk($ips, self::MAX_INSERT) as $chunk) {
            $added += $this->pg()->prepared_query("
                insert into {$this->ipTable}
                       (addr)
                values " . placeholders($chunk, '(?)') .
                " on conflict do nothing",
                ...$chunk
            );
            foreach ($chunk as $addr) {
                self::$db->prepared_query("
                    INSERT IGNORE INTO {$this->ipTable}
                           (addr_a, addr_n)
                    VALUES (     ?, inet_aton(?))
                    ", $addr, $addr
                );
            }
        }
        return $added;
    }

    public function findUsers(MatchCandidate $candidate, bool $loose = true, bool $trackerIps = false): array {
        $uids = $this->findCandidates($candidate, $loose, $trackerIps);

        $matches = [];
        foreach ($uids as $uid) {
            $user = new \Gazelle\User($uid);
            $siteCandidate = $this->siteUserToCandidate($user, $trackerIps);
            $matches[] = [
                'user'  => $user,
                'match' => $siteCandidate->match($candidate)
            ];
        }

        static::sortMatches($matches, $this->sortKey, $this->sortDirection);
        return $matches;
    }

    protected function siteUserToCandidate(\Gazelle\User $user, bool $trackerIps = false): MatchCandidate {
        $siteIps = $this->pg()->all("
            select ip, lower(unnest(seen)) as first_seen, upper(unnest(seen)) as last_seen
            from ip_site_history ih
            inner join {$this->ipTable} s on (s.addr = ih.ip)
            where id_user = ?
            ", $user->id
        );
        $eventIps = $this->pg()->all("
            select ip, lower(seen) as first_seen, upper(seen) as last_seen
            from ip_history ih
            inner join {$this->ipTable} s on (s.addr = ih.ip)
            where id_user = ? and data_origin != 'login-fail'
            ", $user->id
        );
        array_push($siteIps, ...$eventIps);

        if ($trackerIps) {
            self::$db->prepared_query("
                SELECT 
                    IP                        AS ip,
                    from_unixtime(min(mtime)) AS first_seen,
                    NULL                      AS last_seen
                FROM xbt_files_users xfu
                /*INNER JOIN {$this->ipTable} s ON (s.addr_a = xfu.IP)*/
                WHERE xfu.uid = ?
                GROUP BY xfu.IP
                UNION SELECT
                    IP                         AS ip,
                    from_unixtime(min(tstamp)) AS first_seen,
                    from_unixtime(max(tstamp)) AS last_seen
                FROM xbt_snatched xs
                INNER JOIN {$this->ipTable} t ON (t.addr_a = xs.IP)
                WHERE xs.uid = ?
                GROUP BY xs.IP
            ", $user->id, $user->id);
            while ($row = self::$db->next_row(MYSQLI_ASSOC)) {
                $siteIps[] = $row;
            }
        }

        $ips = array_map(fn ($r) => [
            $r['ip'],
            new \DateTimeImmutable($r['first_seen']),
            $r['last_seen'] ? new \DateTimeImmutable($r['last_seen']) : null
        ], $siteIps);

        self::$db->prepared_query("
            SELECT DISTINCT Email
            FROM users_history_emails
            WHERE UserID = ?
            ", $user->id
        );
        $emails = self::$db->collect(0);

        return new MatchCandidate([$user->username()], $emails, $ips);
    }

    // public visibility for testing only
    public function findCandidates(MatchCandidate $candidate, bool $loose = true, bool $trackerIps = false): array {
        $this->addIps(array_keys($candidate->keyedIps()));
        $ids = $this->findSiteUsers();
        if ($trackerIps) {
            array_push($ids, ...$this->findTrackerUsers());
        }
        array_push($ids, ...$this->findByData(
            $candidate->usernames, $candidate->emails, $loose
        ));
        return array_unique($ids);
    }

    protected function findSiteUsers(): array {
        return array_map(fn ($row) => $row['id_user'], $this->pg()->all("
            select id_user
            from ip_site_history ish
            inner join {$this->ipTable} s on (s.addr = ish.ip)
            group by id_user
            union select id_user
            from ip_history ih
            inner join {$this->ipTable} t on (t.addr = ih.ip)
            where ih.data_origin != 'login-fail'
            group by id_user
        "));
    }

    protected function findTrackerUsers(): array {
        // can't use UNION because mysql doesn't support referencing
        // a temporary table multiple times in the same query
        self::$db->prepared_query("
            SELECT uid
            FROM xbt_files_users xfu
            INNER JOIN {$this->ipTable} s ON (s.addr_a = xfu.IP)
            GROUP BY uid
        ");
        $result = self::$db->collect('uid');
        self::$db->prepared_query("
            SELECT uid
            FROM xbt_snatched xs
            INNER JOIN {$this->ipTable} t ON (t.addr_a = xs.IP)
            GROUP BY uid
        ");
        array_push($result, ...self::$db->collect('uid'));
        return array_unique($result);
    }

    protected function findByData(array $usernames, array $emails, bool $loose = true): array {
        if ($emails === [] && $usernames === []) {
            return [];
        }

        $emails = array_unique(array_map(fn ($e) => implode('@', MatchCandidate::cleanupEmail($e)), $emails));
        $usernames = array_unique($usernames);

        $emailSql = 'h.Email';
        if ($loose) {
            $emailSql = 'SUBSTRING_INDEX(h.Email, \'@\', 1)';
            $emails = array_map(fn ($e) => explode('@', $e, 2)[0], $emails);
            array_push($emails, ...$usernames);
            $emails = array_unique($emails);
            $usernames = $emails;
        }

        $query = [];
        if ($emails) {
            $query[] = "
            SELECT h.UserID AS user_id
            FROM users_history_emails AS h
            WHERE $emailSql IN (" . placeholders($emails) . ")
            GROUP BY h.UserID
            ";
        }
        if ($usernames) {
            $query[] = "
            SELECT um.ID AS user_id
            FROM users_main AS um
            WHERE um.username IN (" . placeholders($usernames) . ")
            ";
        }

        self::$db->prepared_query(implode(' UNION ', $query), ...$emails, ...$usernames);
        return self::$db->collect('user_id');
    }

    public static function sortMatches(array &$matches, UserMatchSort $sortKey, Direction $direction): void {
        $direction = $direction === Direction::ascending ? 1 : -1;
        switch ($sortKey) {
            case UserMatchSort::firstDate:
                usort($matches, fn($a, $b) => $direction * gmp_cmp($a['match']->firstDate()?->getTimestamp() ?? PHP_INT_MAX,
                        $b['match']->firstDate()?->getTimestamp() ?? PHP_INT_MAX));
                break;
            case UserMatchSort::lastDate:
                usort($matches, fn($a, $b) => $direction * gmp_cmp($a['match']->lastDate()?->getTimestamp() ?? 0,
                        $b['match']->lastDate()?->getTimestamp() ?? 0));
                break;
            default:  // score
                usort($matches, fn($a, $b) => $direction * gmp_cmp($a['match']->score(), $b['match']->score()));
                break;
        }
    }

    public static function cache(MatchCandidate $candidate, array $matches, string $text, \Gazelle\User $owner): string {
        $token = randomString(16);
        $key = sprintf(static::CACHE_KEY, $owner->id, $token);
        self::$cache->cache_value($key, [$matches, $text, count($candidate->ips), count($candidate->emails)], 3600);
        return $token;
    }

    public static function fromCache(string $token, \Gazelle\User $owner): array|false {
        $key = sprintf(static::CACHE_KEY, $owner->id, $token);
        return self::$cache->get_value($key);
    }
}
