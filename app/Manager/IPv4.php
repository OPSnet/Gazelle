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

    public function registerApi(User $user, string $ip): int {
        $affected = $this->pg()->query("
            insert into ip_history
                   (id_user, ip, data_origin)
            values ($1,      $2, 'token')
            on conflict (id_user, ip, data_origin) do update set
                total = ip_history.total + 1,
                seen = tstzrange(lower(ip_history.seen), now())
            ", $user->id, $ip
        );
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

    public function userTotal(User $user): int {
        $cond = ['id_user = $1'];
        $args = [$user->id];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = 'ip::text ~* ' . $this->pg()->placeholder(count($args));
            $args[] = $this->filterIpaddrRegexp;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = 'ip <<= ' . $this->pg()->placeholder(count($args));
            $args[] = $this->filterIpaddr;
        }
        if (isset($this->filterBefore)) {
            $cond[] = 'lower(seen) <= now() - '  . $this->pg()->placeholder(count($args)) . '::interval';
            $args[] = $this->filterBefore;
        }
        if (isset($this->filterAfter)) {
            $cond[] = 'lower(seen) >= now() - '  . $this->pg()->placeholder(count($args)) . '::interval';
            $args[] = $this->filterAfter;
        }
        $where  = implode(' and ', $cond);
        return $this->pg()->scalarex(<<<END_SQL
            with history as (
                select distinct ip from ip_site_history
                where $where
                union
                select distinct ip from ip_history
                where $where
                  and data_origin != 'login-fail'
            )
            select count(ip) from history
            END_SQL, ...$args
        );
    }

    public function userPage(User $user, int $limit, int $offset): array {
        $cond = ['ish.id_user = $1'];
        $args = [$user->id];
        if (isset($this->filterIpaddrRegexp)) {
            $cond[] = 'ish.ip::text ~* ' . $this->pg()->placeholder(count($args));
            $args[] = $this->filterIpaddrRegexp;
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = 'ish.ip <<= ' . $this->pg()->placeholder(count($args));
            $args[] = $this->filterIpaddr;
        }
        $where  = implode(' and ', $cond);
        $where2 = str_replace('ish.', 'ih.', $where);
        $args[] = $limit;
        $args[] = $offset;
        return $this->pg()->allex(<<<END_SQL
            with seen_ranges as (
                select ip, id_user, unnest(seen) seen_time from ip_site_history
                union all
                select ip, id_user, seen seen_time from ip_history
                where data_origin != 'login-fail'
            )
            select ip as ip_addr,
                count(distinct id_user) as nr_users,
                string_agg(
                    concat(id_user, '/', lower(seen_time), '/', upper(seen_time)), ','
                    order by id_user != \$1, lower(seen_time) desc
                ) AS ranges,
                min(lower(seen_time)) as min_start,
                max(upper(seen_time)) as max_end,
                exists(select 1 from ip_ban ib where ib.ip = ih.ip and ib.is_active) as is_banned
            from seen_ranges ih
            where ip in (
                select distinct ish.ip
                from ip_site_history ish
                where $where
                union
                select distinct ih.ip
                from ip_history ih
                where $where2
                  and data_origin != 'login-fail'
            )
            group by ip
            order by max_end desc, ip
            END_SQL
            . ' limit ' . $this->pg()->placeholder(count($args) - 2)
            . ' offset ' . $this->pg()->placeholder(count($args) - 1),
            ...$args
        );
    }
}
