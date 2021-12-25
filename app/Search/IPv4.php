<?php

namespace Gazelle\Search;

class IPv4 extends \Gazelle\Base {

    public const ASC = 0;
    public const DESC = 1;

    public const START = 0;
    public const END   = 1;
    public const IP    = 2;

    /**
     * Take a freeform slab of text and search for dotted quads.
     * Create a table with (addr_a, addr_n) rows (ascii and numeric)
     * E.g. 1.1.1.1 is stored as ('1.1.1.1', 16843009)
     */

    protected string $name;
    protected int $column = 0;
    protected int $direction = 0;

    public function setColumn(int $column) {
        $this->column = $column;
        return $this;
    }

    public function setDirection(int $direction) {
        $this->direction = $direction;
        return $this;
    }

    public function create(string $name) {
        $this->name = $name;
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE {$this->name} (
                addr_n integer(10) unsigned NOT NULL PRIMARY KEY,
                addr_a varchar(15) CHARACTER SET ASCII NOT NULL,
                KEY(addr_a)
            )
        ");
        return $this;
    }

    public function add(string $text): int {
        if (!preg_match_all('/(\d{1,3}(?:\.\d{1,3}){3})/', $text, $match)) {
            return 0;
        }
        $quad = array_unique($match[0]);
        $added = 0;
        foreach ($quad as $addr) {
            self::$db->prepared_query("
                INSERT INTO {$this->name}
                       (addr_a, addr_n)
                VALUES (     ?, inet_aton(?))
                ", $addr, $addr
            );
            $added += self::$db->affected_rows();
        }
        return $added;
    }

    public function ipList(): string {
        self::$db->prepared_query("
            SELECT addr_n FROM {$this->name} ORDER BY addr_n
        ");
        return implode(',', array_map(fn ($n) => base_convert($n, 10, 36), self::$db->collect(0)));
    }

    public function siteTotal(): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM users_history_ips uhi
            INNER JOIN {$this->name} s ON (s.addr_a = uhi.IP)
        ");
    }

    public function sitePage(\Gazelle\Manager\User $userMan, int $limit, int $offset): array {
        $column = ['uhi.StartTime', 'uhi.EndTime', "s.addr_n"][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        self::$db->prepared_query($sql = "
            SELECT uhi.StartTime AS start,
                uhi.EndTime      AS 'end',
                uhi.IP           AS ipv4,
                uhi.UserID       AS user_id
            FROM users_history_ips uhi
            INNER JOIN {$this->name} s ON (s.addr_a = uhi.IP)
            ORDER BY $column $direction
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        $list =  self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['user'] = $userMan->findById($row['user_id']);
        }
        return $list;
    }

    public function snatchTotal(): int {
        return self::$db->scalar("
            SELECT count(DISTINCT xs.uid)
            FROM xbt_snatched xs
            INNER JOIN {$this->name} s ON (s.addr_a = xs.IP)
        ");
    }

    public function snatchPage(\Gazelle\Manager\User $userMan, int $limit, int $offset): array {
        $column = ['from_unixtime(min(xs.tstamp))', 'from_unixtime(max(xs.tstamp))', "s.addr_n"][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        self::$db->prepared_query($sql = "
            SELECT from_unixtime(min(xs.tstamp)) AS start,
                from_unixtime(max(xs.tstamp))    AS 'end',
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
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['user'] = $userMan->findById($row['user_id']);
        }
        return $list;
    }

    public function trackerTotal(): int {
        return self::$db->scalar("
            SELECT count(DISTINCT xfu.uid)
            FROM xbt_files_users xfu
            INNER JOIN {$this->name} s ON (s.addr_a = xfu.IP)
        ");
    }

    public function trackerPage(\Gazelle\Manager\User $userMan, int $limit, int $offset): array {
        $column = ['from_unixtime(min(xfu.mtime))', 'from_unixtime(max(xfu.mtime))', "s.addr_n"][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        self::$db->prepared_query($sql = "
            SELECT from_unixtime(min(xfu.mtime)) AS start,
                from_unixtime(max(xfu.mtime + xfu.timespent * 60)) AS 'end',
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
        $list =  self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['user'] = $userMan->findById($row['user_id']);
        }
        return $list;
    }
}
