<?php

namespace Gazelle\Manager;

use Gazelle\Util\SortableTableHeader as TableHeader;

/**
 * This class handles banned IP addreses.
 */

class Ban extends \Gazelle\Base {
    protected bool $filterActive;
    protected string $filterBegin;
    protected string $filterEnd;
    protected string $filterIpaddr;
    protected string $filterNotes;

    public function flush(): static {
        unset(
            $this->filterActive,
            $this->filterEnd,
            $this->filterBegin,
            $this->filterIpaddr,
            $this->filterNotes
        );
        return $this;
    }

    /**
     * Create an ip address ban on an IPv4 CIDR.
     * Creating a row that already exists will automatically activate
     * the rule (and will have to be deactivated manually if that is necessary).
     * Cannot always pass in a user object because someone may be trying to
     * force entry with an ID that does not correspond to a user, e.g. due
     * to a password brute-forcing attempt.
     */
    public function create(string $ipaddr, string $note, \Gazelle\User|null $user = null): \Gazelle\Ban {
        $this->pg()->begin();
        $cidrList = $this->pg()->columnex("
            select ip from ip_ban where ip && $1::inet
            ", $ipaddr
        );
        if (count($cidrList) > 1) {
            // the new  CIDR overlaps with more than one previous range
            $inet = $ipaddr;
            foreach ($cidrList as $cidr) {
                $inet = $this->pg()->scalarex("
                    select inet_merge($1::inet, $2::inet)
                    ", $inet, $cidr
                );
            }
            // grab the notes from the existing ranges
            $noteList = $this->pg()->columnex("
                select ib.ip || ':' || ibh.note
                from ip_ban_hist ibh
                inner join ip_ban ib using (id_ip_ban)
                where ip && $1::inet
                ", $ipaddr
            );
            // before we delete them
            $this->pg()->query("
                delete from ip_ban where ip && $1::inet
                ", $ipaddr
            );
            // and create a new range than encompasses them all
            $id = $this->pg()->scalarex("
                insert into ip_ban (ip) values (inet_merge($1::inet, $2::inet))
                returning id_ip_ban
                ", $ipaddr, $inet
            );
            $this->pg()->query("
                insert into ip_ban_hist
                       (id_ip_ban, note, id_user)
                values ($1,        $2,   $3)
                ", $id,
                   trim("$note (extended " . implode(', ', $noteList) . ")"),
                   (int)$user?->id
            );
        } else {
            // 0 or 1 other ranges, insert or merge
            [$id, $action] = $this->pg()->rowex("
                with add as (select network($1::inet) as ip)
                merge into ip_ban ib using add on (ib.ip && add.ip)
                    when not matched then insert (ip) values (ip)
                    when matched then update set
                        ip        = network(inet_merge(ib.ip, add.ip)),
                        is_active = true
                returning id_ip_ban, merge_action()
                ", $ipaddr
            );
            $this->pg()->query("
                insert into ip_ban_hist
                       (id_ip_ban, note, id_user)
                values ($1,        $2,   $3)
                ", $id,
                   $action === 'INSERT' ? $note : "$note (merged $ipaddr)",
                   (int)$user?->id
            );
        }
        $this->pg()->commit();
        $this->flush();
        return new \Gazelle\Ban($id);
    }

    public function findById(int $id): ?\Gazelle\Ban {
        $banId = (int)$this->pg()->scalarex("
            select id_ip_ban from ip_ban where id_ip_ban = $1
            ", $id
        );
        return $banId ? new \Gazelle\Ban($banId) : null;
    }

    public function findByIp(string $ip): ?\Gazelle\Ban {
        $banId = (int)$this->pg()->scalarex("
            select id_ip_ban from ip_ban where ip && $1::inet
            ", $ip
        );
        return $this->findById($banId);
    }

    public function header(): TableHeader {
        return new TableHeader('created', [
            'ip'      => ['dbColumn' => 'ip',        'defaultSort' => 'asc',  'text' => 'IP CIDR'],
            'note'    => ['dbColumn' => 'note',      'defaultSort' => 'asc',  'text' => 'Notes'],
            'id_user' => ['dbColumn' => 'id_user',   'defaultSort' => 'asc',  'text' => 'Added By'],
            'created' => ['dbColumn' => 'created',   'defaultSort' => 'desc', 'text' => 'Created'],
            'active'  => ['dbColumn' => 'is_active', 'defaultSort' => 'desc', 'text' => 'Active'],
            'total'   => ['dbColumn' => 'total',     'defaultSort' => 'desc', 'text' => 'Total'],
        ]);
    }

    /**
     * Is an IP address banned?
     */
    public function isBanned(string $ipaddr): bool {
        return (bool)$this->pg()->scalarex("
            select 1
            from ip_ban
            where is_active is true
                and ip && $1::inet
            ", $ipaddr
        );
    }

    public function setFilterActive(bool $filterActive): static {
        $this->filterActive = $filterActive;
        return $this;
    }

    public function setFilterIpaddr(string $filterIpaddr): static {
        $this->filterIpaddr = $filterIpaddr;
        return $this;
    }

    public function setFilterNotes(string $filterNotes): static {
        $this->filterNotes = $filterNotes;
        return $this;
    }

    public function setFilterTime(string $begin, string $end): static {
        $this->filterBegin = $begin;
        $this->filterEnd   = $end;
        return $this;
    }

    public function queryBase(): array {
        $cond = [];
        $args = [];
        if (isset($this->filterActive)) {
            $cond[] = "ib.is_active = ?";
            $args[] = $this->filterActive;
        }
        if (isset($this->filterBegin)) {
            $cond[] = "ibh.created between ? and ?";
            array_push($args, $this->filterBegin, $this->filterEnd);
        }
        if (isset($this->filterIpaddr)) {
            $cond[] = "ib.ip && ?";
            $args[] = $this->filterIpaddr;
        }
        if (isset($this->filterNotes)) {
            $cond[] = "ibh.note ~ ?";
            $args[] = $this->filterNotes;
        }
        return [
            "from ip_ban ib
            inner join ip_ban_hist ibh using (id_ip_ban)
            inner join h using (id_ip_ban, id_ip_ban_hist)"
                . (empty($cond) ? '' : (' where ' . implode(' and ', $cond))),
            $args
        ];
    }

    public function total(): int {
        [$from, $args] = $this->queryBase();
        return (int)$this->pg()->scalar("
            with h as (
                select id_ip_ban,
                    max(id_ip_ban_hist) as id_ip_ban_hist
                from ip_ban_hist
                group by id_ip_ban
            )
            select count(h.id_ip_ban)
            $from
            ", ...$args
        );
    }

    public function page(int $limit, int $offset): array {
        [$from, $args] = $this->queryBase();
        $header = $this->header();
        return $this->pg()->all("
            with h as (
                select id_ip_ban,
                    max(id_ip_ban_hist) as id_ip_ban_hist,
                    count(*)            as total
                from ip_ban_hist
                group by id_ip_ban
            )
            select
                ib.id_ip_ban as id,
                ib.is_active,
                ib.ip,
                host(ib.ip)            as lo,
                host(broadcast(ib.ip)) as hi,
                ibh.id_user,
                ibh.created,
                ibh.note,
                h.total
            $from
            order by {$header->orderBy()} {$header->dir()}
            limit ? offset ?
            ", ...array_merge($args, [$limit, $offset])
        );
    }
}
