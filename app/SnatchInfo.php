<?php

namespace Gazelle;

class SnatchInfo extends Base {
    protected string $searchField;
    protected int|string $searchValue;

    public function setContextUser(User $user): static {
        $this->searchField = 'xs.uid';
        $this->searchValue = $user->id();
        return $this;
    }

    public function setContextIpaddr(string $ipaddr): static {
        $this->searchField = 'xs.IP';
        $this->searchValue = $ipaddr;
        return $this;
    }

    public function total(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM xbt_snatched xs WHERE {$this->searchField} = ?
            ", $this->searchValue
        );
    }

    public function summary(): array {
        self::$db->prepared_query("
            SELECT xs.IP,
                count(*) AS total,
                from_unixtime(min(xs.tstamp)) AS first,
                from_unixtime(max(xs.tstamp)) AS last
            FROM xbt_snatched xs
            WHERE {$this->searchField} = ?
            GROUP BY xs.IP
            ORDER BY max(xs.tstamp) DESC
            ", $this->searchValue
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function page(int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT xs.IP AS ip,
                xs.uid,
                coalesce(um.Username, 'System') AS username,
                xs.fid,
                from_unixtime(xs.tstamp) AS date,
                tg.Name AS name
            FROM xbt_snatched xs
            LEFT JOIN users_main um ON (um.ID = xs.uid)
            LEFT JOIN torrents t ON (t.ID = xs.fid)
            LEFT JOIN torrents_group tg ON (tg.ID = t.GroupID)
            WHERE {$this->searchField} = ?
            ORDER BY xs.tstamp DESC
            LIMIT ? OFFSET ?
            ", $this->searchValue, $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
