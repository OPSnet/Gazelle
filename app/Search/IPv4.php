<?php

namespace Gazelle\Search;

class IPv4 extends \Gazelle\Base {
    final public const ASC = 0;
    final public const DESC = 1;

    final public const START = 0;
    final public const END   = 1;
    final public const IP    = 2;
    final public const TOTAL = 3;

    final protected const MAX_INSERT = 1000;

    /**
     * Take a freeform slab of text and search for dotted quads.
     * Create a table with (addr_a, addr_n) rows (ascii and numeric)
     * E.g. 1.1.1.1 is stored as ('1.1.1.1', 16843009)
     */

    protected string $name;
    protected int $column = 0;
    protected int $direction = 0;

    public function __construct(
        protected ASN $asn,
    ) {}

    public function __destruct() {
        if (isset($this->name)) {
            self::$db->dropTemporaryTable($this->name);
        }
    }

    public function setColumn(int $column): static {
        $this->column = $column;
        return $this;
    }

    public function setDirection(int $direction): static {
        $this->direction = $direction;
        return $this;
    }

    public function create(): static {
        $this->name = 'tmp_ipsearch_' . str_replace(['.', ' '], '', microtime());
        $this->pg()->prepared_query("drop table if exists " . $this->name);
        $this->pg()->prepared_query("
            create temporary table {$this->name} (
                addr inet primary key
            )
        ");
        self::$db->dropTemporaryTable($this->name);
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE {$this->name} (
                addr_n integer unsigned NOT NULL PRIMARY KEY,
                addr_a varchar(15) CHARACTER SET ASCII NOT NULL,
                KEY(addr_a)
            )
        ");
        return $this;
    }

    public function add(string $text): int {
        if (!preg_match_all('/(((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4})/', $text, $match)) {
            return 0;
        }
        $quad = array_unique($match[0]);
        $added = 0;
        foreach (array_chunk($quad, self::MAX_INSERT) as $chunk) {
            $added += $this->pg()->prepared_query("
                insert into {$this->name}
                       (addr)
                values " . placeholders($chunk, '(?)') .
                " on conflict do nothing",
                 ...$chunk
            );
            foreach ($chunk as $addr) {
                self::$db->prepared_query("
                    INSERT IGNORE INTO {$this->name}
                           (addr_a, addr_n)
                    VALUES (     ?, inet_aton(?))
                    ", $addr, $addr
                );
            }
        }
        return $added;
    }

    public function ipList(): string {
        self::$db->prepared_query("
            SELECT addr_n FROM {$this->name} ORDER BY addr_n
        ");
        return implode('.', array_map(fn ($n) => base_convert($n, 10, 36), self::$db->collect(0)));
    }

    public function siteTotal(): int {
        return (int)$this->pg()->scalar("
            select count(distinct id_user)
            from ip_site_history ih
            inner join {$this->name} s on (s.addr = ih.ip)
        ");
    }

    public function siteList(int $limit, int $offset): array {
        $column = ['lower(range_agg(ih.seen))', 'upper(range_agg(ih.seen))', 'min(ip)', 'count(distinct ip)'][$this->column];
        $direction = ['asc', 'desc'][$this->direction];

        $result = $this->pg()->all("
            with cte as (
                select 
                  id_user,
                  row_number() over (order by $column $direction)
                from ip_site_history ih
                inner join {$this->name} s on (s.addr = ih.ip)
                group by id_user
                limit ? offset ?
            )
            select
              ip,
              ih.id_user,
              to_char(lower(unnest(seen)), 'YYYY-MM-DD HH24:MI') first_seen,
              to_char(upper(unnest(seen)), 'YYYY-MM-DD HH24:MI') last_seen
            from ip_site_history ih
            inner join {$this->name} s on (s.addr = ih.ip)
            inner join cte on (cte.id_user = ih.id_user)
            order by row_number, seen, ip
            ", $limit, $offset
        );
        $asnList = $this->asn->findByIpList(array_unique(array_map(fn ($r) => $r['ip'], $result)));
        foreach ($result as &$row) {
            $row['cc']     = $asnList[$row['ip']]['cc'];
            $row['is_tor'] = $asnList[$row['ip']]['is_tor'];
            $row['n']      = $asnList[$row['ip']]['n'];
            $row['name']   = $asnList[$row['ip']]['name'];
        }
        return $result;
    }

    public function snatchTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(DISTINCT xs.uid)
            FROM xbt_snatched xs
            INNER JOIN {$this->name} s ON (s.addr_a = xs.IP)
        ");
    }

    public function snatchList(int $limit, int $offset): array {
        $column = ['from_unixtime(min(xs.tstamp))', 'from_unixtime(max(xs.tstamp))', 'inet_aton(s.addr_n)', 'count(*)'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        self::$db->prepared_query("
            SELECT from_unixtime(min(xs.tstamp)) AS first_seen,
                from_unixtime(max(xs.tstamp))    AS last_seen,
                count(*)                         AS total,
                xs.IP                            AS ipv4,
                xs.uid                           AS user_id
            FROM xbt_snatched xs
            INNER JOIN {$this->name} s ON (s.addr_a = xs.IP)
            GROUP BY xs.IP, xs.uid
            ORDER BY $column $direction
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        $asnList = $this->asn->findByIpList(self::$db->collect('ipv4'));
        $list = self::$db->to_array(false, MYSQLI_ASSOC);
        foreach ($list as &$row) {
            $row['cc']     = $asnList[$row['ipv4']]['cc'];
            $row['is_tor'] = $asnList[$row['ipv4']]['is_tor'];
            $row['n']      = $asnList[$row['ipv4']]['n'];
            $row['name']   = $asnList[$row['ipv4']]['name'];
        }
        return $list;
    }

    public function trackerTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(DISTINCT xfu.uid)
            FROM xbt_files_users xfu
            INNER JOIN {$this->name} s ON (s.addr_a = xfu.IP)
        ");
    }

    public function trackerList(int $limit, int $offset): array {
        $column = ['from_unixtime(min(xfu.mtime))', 'from_unixtime(max(xfu.mtime))', 'inet_aton(s.addr_n)', 'count(*)'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        self::$db->prepared_query("
            SELECT from_unixtime(min(xfu.mtime)) AS first_seen,
                from_unixtime(max(xfu.mtime + xfu.timespent * 60)) AS last_seen,
                count(*)                         AS total,
                xfu.ip                           AS ipv4,
                xfu.uid                          AS user_id
            FROM xbt_files_users xfu
            INNER JOIN {$this->name} s ON (s.addr_a = xfu.IP)
            GROUP BY xfu.IP, xfu.uid
            ORDER BY $column $direction
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        $asnList = $this->asn->findByIpList(self::$db->collect('ipv4'));
        $list = self::$db->to_array(false, MYSQLI_ASSOC);
        foreach ($list as &$row) {
            $row['cc']     = $asnList[$row['ipv4']]['cc'];
            $row['is_tor'] = $asnList[$row['ipv4']]['is_tor'];
            $row['n']      = $asnList[$row['ipv4']]['n'];
            $row['name']   = $asnList[$row['ipv4']]['name'];
        }
        return $list;
    }
}
